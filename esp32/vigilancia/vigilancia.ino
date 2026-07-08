/*
 * vigilancia.ino — ESP32 NodeMCU
 * Sistema de Vigilância Inteligente — SecureNode
 *
 * Sensores:
 *   - KY-037 Sensor de Som (AO)      → GPIO 34
 *   - KY-026 Sensor de Fogo (DO)     → GPIO 27
 *
 * Atuadores:
 *   - KY-016 LED RGB (R / G / B)     → GPIO 25 / 26 / 32
 *   - KY-019 Módulo Relé             → GPIO 33
 *
 * Lógica dos sensores:
 *   - KY-037 Som:  leitura AO > threshold → som detetado
 *   - KY-026 Fogo: DO = 1 → fogo | DO = 0 → sem fogo
 *
 * LED RGB: Verde = normal | Azul = som | Vermelho = fogo
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

// ── Configuração ──────────────────────────────────────────────────────────────

const char* SSID     = "NOME_DO_WIFI";
const char* PASSWORD = "PASSWORD_DO_WIFI";
const char* API_URL  = "https://SEU_SERVIDOR/api/api.php";
const char* API_KEY  = "SUA_API_KEY";

// ── Pinos GPIO ────────────────────────────────────────────────────────────────

#define PINO_SOM    34   // KY-037 AO (analogRead)
#define PINO_FOGO   27   // KY-026 DO
#define PINO_LED_R  25   // KY-016 R
#define PINO_LED_G  26   // KY-016 G
#define PINO_LED_B  32   // KY-016 B
#define PINO_RELE   33   // KY-019 IN

// ── Intervalos (ms) ───────────────────────────────────────────────────────────

#define INTERVALO_SENSORES  300    // lê sensores a cada 300ms
#define INTERVALO_POLLING   1500   // faz GET ao relé a cada 1.5s
#define INTERVALO_WIFI      10000  // verifica ligação Wi-Fi a cada 10s

// ── Calibração do sensor de som ───────────────────────────────────────────────

#define SOM_THRESHOLD    3030  // valor de repouso ~2990; som dispara acima de 3030
#define SOM_HOLD_DURACAO 1000  // mantém som=1 durante 1s após última deteção
#define SOM_COOLDOWN     5000  // após deteção, ignora som durante 5s (evita spam)

// ── Relé por fogo ─────────────────────────────────────────────────────────────

#define FOGO_RELE_DURACAO 500  // relé desliga 0.5s após fogo terminar

// ── Estado ────────────────────────────────────────────────────────────────────

int  estado_som_anterior  = -1;
int  estado_fogo_anterior = -1;
int  estado_led_anterior  = -1;
int  estado_rele_anterior = -1;

unsigned long ultimo_sensor       = 0;
unsigned long ultimo_polling      = 0;
unsigned long ultimo_wifi         = 0;
unsigned long som_ultimo_trigger  = 0;
unsigned long som_ultimo_post     = 0;
unsigned long rele_ultimo_trigger = 0;

// Distingue se o relé foi ativado por fogo (para desligar automaticamente)
bool rele_por_fogo = false;

// Debounce do fogo: exige 2 leituras iguais consecutivas antes de confirmar
int fogo_leitura_anterior = -1;
int fogo_contador         = 0;

// ── Protótipos ────────────────────────────────────────────────────────────────

bool   enviarValor(const char* nome, int valor);
String lerValor(const char* nome);
void   ledVerde();
void   ledVermelho();
void   ledAzul();
void   verificarWiFi();

// ── Setup ─────────────────────────────────────────────────────────────────────

void setup() {
    Serial.begin(115200);
    Serial.println("\n== SecureNode — ESP32 ==");

    pinMode(PINO_SOM,   INPUT);
    pinMode(PINO_FOGO,  INPUT);
    pinMode(PINO_LED_R, OUTPUT);
    pinMode(PINO_LED_G, OUTPUT);
    pinMode(PINO_LED_B, OUTPUT);
    pinMode(PINO_RELE,  OUTPUT);

    ledVerde();
    digitalWrite(PINO_RELE, LOW);

    Serial.print("[WiFi] A ligar a ");
    Serial.print(SSID);
    WiFi.begin(SSID, PASSWORD);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\n[WiFi] Ligado! IP: " + WiFi.localIP().toString());

    // Garante estado limpo no servidor ao arrancar
    enviarValor("som",  0);
    enviarValor("fogo", 0);
    enviarValor("rele", 0);
    Serial.println("[INICIO] Sistema em execução.\n");
}

// ── Loop ──────────────────────────────────────────────────────────────────────

void loop() {
    unsigned long agora = millis();

    if (agora - ultimo_wifi >= INTERVALO_WIFI) {
        verificarWiFi();
        ultimo_wifi = agora;
    }

    if (agora - ultimo_sensor >= INTERVALO_SENSORES) {
        lerSensores();
        ultimo_sensor = agora;
    }

    if (agora - ultimo_polling >= INTERVALO_POLLING) {
        pollingAtuadores();
        ultimo_polling = agora;
    }
}

// ── Sensores ──────────────────────────────────────────────────────────────────

void lerSensores() {
    unsigned long agora = millis();

    // Som: threshold fixo + hold de 1s + cooldown de 5s para evitar spam
    int som_raw = (analogRead(PINO_SOM) > SOM_THRESHOLD) ? 1 : 0;
    if (som_raw == 1) som_ultimo_trigger = agora;
    int som = (agora - som_ultimo_trigger < SOM_HOLD_DURACAO) ? 1 : 0;

    if (som != estado_som_anterior) {
        // Cooldown só se aplica ao ligar (som=1); desligar (som=0) envia sempre
        if (som == 0 || (agora - som_ultimo_post >= SOM_COOLDOWN)) {
            Serial.println(som ? "[ALERTA] Som detetado!" : "[OK] Silêncio");
            enviarValor("som", som);
            if (som == 1) som_ultimo_post = agora;
            estado_som_anterior = som;
        }
    }

    // Fogo: debounce de 2 leituras consecutivas iguais para evitar falsos positivos
    int fogo_raw = digitalRead(PINO_FOGO);
    if (fogo_raw == fogo_leitura_anterior) {
        fogo_contador++;
    } else {
        fogo_contador = 0;
    }
    fogo_leitura_anterior = fogo_raw;

    int fogo = (fogo_contador >= 2) ? fogo_raw : estado_fogo_anterior;
    if (fogo == -1) fogo = 0;

    if (fogo != estado_fogo_anterior) {
        Serial.println(fogo ? "[ALERTA] Fogo detetado!" : "[OK] Sem fogo");
        enviarValor("fogo", fogo);
        if (fogo == 1) {
            // Ativa o relé imediatamente quando fogo é confirmado
            rele_ultimo_trigger = agora;
            rele_por_fogo = true;
            digitalWrite(PINO_RELE, HIGH);
            enviarValor("rele", 1);
            estado_rele_anterior = 1;
        }
        estado_fogo_anterior = fogo;
    }

    // Desliga o relé 0.5s após fogo terminar (só se foi o fogo que o ligou)
    if (rele_por_fogo && fogo == 0 && agora - rele_ultimo_trigger >= FOGO_RELE_DURACAO) {
        digitalWrite(PINO_RELE, LOW);
        enviarValor("rele", 0);
        estado_rele_anterior = 0;
        rele_por_fogo = false;
    }

    // LED reflete o estado mais crítico: vermelho > azul > verde
    int led_novo = (fogo == 1) ? 2 : (som == 1) ? 1 : 0;
    if (led_novo != estado_led_anterior) {
        if      (led_novo == 2) ledVermelho();
        else if (led_novo == 1) ledAzul();
        else                    ledVerde();
        estado_led_anterior = led_novo;
    }
}

// ── Polling atuadores ─────────────────────────────────────────────────────────

void pollingAtuadores() {
    // O relé pode ser comandado pelo dashboard — lê o valor atual da API
    String rele_api = lerValor("rele");

    if (rele_api.length() > 0) {
        int estado = rele_api.toInt();
        if (estado != estado_rele_anterior) {
            digitalWrite(PINO_RELE, estado ? HIGH : LOW);
            Serial.println(estado ? "[RELE] LIGADO" : "[RELE] DESLIGADO");
            estado_rele_anterior = estado;
        }
    }
}

// ── HTTP ──────────────────────────────────────────────────────────────────────

bool enviarValor(const char* nome, int valor) {
    if (WiFi.status() != WL_CONNECTED) return false;

    // setInsecure() aceita qualquer certificado SSL — necessário pois a rede
    // escolar não tem o certificado do servidor na cadeia de confiança do ESP32
    WiFiClientSecure client;
    client.setInsecure();
    client.setTimeout(4);

    HTTPClient http;
    http.begin(client, API_URL);
    http.setTimeout(4000);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-API-Key", API_KEY);

    String corpo = "nome=" + String(nome) + "&valor=" + String(valor);
    int codigo = http.POST(corpo);
    Serial.println("[API] POST " + String(nome) + "=" + String(valor) + " → " + String(codigo));
    http.end();
    return (codigo == 200);
}

String lerValor(const char* nome) {
    if (WiFi.status() != WL_CONNECTED) return "";

    WiFiClientSecure client;
    client.setInsecure();
    client.setTimeout(4);

    HTTPClient http;
    http.begin(client, String(API_URL) + "?nome=" + String(nome));
    http.setTimeout(4000);

    int codigo = http.GET();
    String resposta = (codigo == 200) ? http.getString() : "";
    http.end();
    resposta.trim();
    return resposta;
}

// ── Auxiliares ────────────────────────────────────────────────────────────────

void ledVerde()    { digitalWrite(PINO_LED_R, LOW);  digitalWrite(PINO_LED_G, HIGH); digitalWrite(PINO_LED_B, LOW);  }
void ledVermelho() { digitalWrite(PINO_LED_R, HIGH); digitalWrite(PINO_LED_G, LOW);  digitalWrite(PINO_LED_B, LOW);  }
void ledAzul()     { digitalWrite(PINO_LED_R, LOW);  digitalWrite(PINO_LED_G, LOW);  digitalWrite(PINO_LED_B, HIGH); }

void verificarWiFi() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WiFi] Desligado — a reconectar...");
        WiFi.disconnect();
        WiFi.begin(SSID, PASSWORD);
        int t = 0;
        while (WiFi.status() != WL_CONNECTED && t < 20) { delay(500); t++; }
        if (WiFi.status() == WL_CONNECTED)
            Serial.println("[WiFi] Reconectado! IP: " + WiFi.localIP().toString());
    }
}
