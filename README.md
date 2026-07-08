# SecureNode — Sistema de Vigilância Inteligente IoT

Sistema de vigilância em tempo real com Raspberry Pi 4B e ESP32, desenvolvido no âmbito da unidade curricular de Tecnologias da Internet (ESTG — IPLeiria).

---

## Componentes

**Raspberry Pi 4B**
- DHT11 — Temperatura e humidade
- KS0052 PIR — Deteção de movimento
- KY-031 — Sensor de choque/vibração
- Buzzer passivo — Alarme PWM
- DroidCam — Stream de câmara ao vivo via Wi-Fi

**ESP32 NodeMCU**
- KY-037 — Sensor de som (pino analógico)
- KY-026 — Sensor de fogo
- KY-016 — LED RGB (estado do sistema)
- KY-019 — Módulo relé

---

## Arquitetura

```
[Raspberry Pi] ──POST──▶ [API PHP no Servidor] ◀──GET── [ESP32]
[ESP32]        ──POST──▶ [API PHP no Servidor] ◀──GET── [Raspberry Pi]
[Browser]      ──AJAX──▶ [API PHP no Servidor]
```

Nenhum dispositivo comunica diretamente com outro — o servidor é sempre o intermediário.

---

## Funcionalidades

- Dashboard web em tempo real com AJAX (sensores a cada 2s, gráficos a cada 15s)
- Gráficos de temperatura e humidade com Chart.js
- Stream de câmara ao vivo (MJPEG)
- Histórico de eventos e imagens capturadas automaticamente
- Controlo de atuadores pelo dashboard (buzzer e relé)
- 3 níveis de acesso: Admin, Operador, Visitante
- Autenticação por sessão PHP (utilizadores) e API Key (dispositivos)
- Passwords em hash bcrypt

---

## Estrutura do Projeto

```
vigilancia/
├── api/                  # API REST PHP
│   ├── api.php           # Endpoints GET e POST
│   ├── upload.php        # Upload de imagens
│   └── files/            # Dados dos sensores (valor, hora, log)
├── config/               # Credenciais (não incluídas no repositório)
│   ├── api_keys.example.txt
│   ├── utilizadores.example.txt
│   └── config.example.php
├── includes/             # Auth, header, footer, navbar
├── raspberry/
│   └── vigilancia.py     # Código do Raspberry Pi
├── esp32/
│   └── vigilancia/
│       └── vigilancia.ino  # Código do ESP32
├── dashboard.php
├── historico.php
├── historico_imagens.php
└── index.php
```

---

## Configuração

**1. Credenciais PHP**

Copiar os ficheiros de exemplo e preencher:
```
config/api_keys.example.txt     → config/api_keys.txt
config/utilizadores.example.txt → config/utilizadores.txt
config/config.example.php       → config/config.php
```

**2. Raspberry Pi**

Editar `raspberry/vigilancia.py` e definir o IP do DroidCam:
```python
WEBCAM_SOURCE = "http://IP_DO_TELEMOVEL:4747/video"
```

Instalar dependências e correr:
```bash
pip install -r raspberry/requirements.txt
python3 raspberry/vigilancia.py
```

**3. ESP32**

Editar `esp32/vigilancia/vigilancia.ino` e definir as credenciais Wi-Fi:
```cpp
const char* SSID     = "NOME_DO_WIFI";
const char* PASSWORD = "PASSWORD_DO_WIFI";
```

Fazer upload pelo Arduino IDE.

---

## Tecnologias

- Python 3, RPi.GPIO, adafruit-dht, OpenCV, requests
- Arduino C++ (ESP32), HTTPClient, WiFiClientSecure
- PHP 8 nativo, sessões, password_hash/verify
- HTML, CSS, Bootstrap 5, JavaScript, Chart.js
- AJAX com fetch(), MJPEG stream, API REST sem frameworks
