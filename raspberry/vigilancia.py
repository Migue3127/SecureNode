"""
vigilancia.py — Raspberry Pi 4B
Sistema de Vigilância Inteligente — SecureNode

Sensores:
  - DHT11 Temperatura/Humidade  → GPIO 4
  - KS0052 PIR Movimento        → GPIO 17
  - KY-031 Sensor de Choque     → GPIO 27

Atuadores:
  - Buzzer Passivo              → GPIO 22

Webcam: DroidCam via Wi-Fi, stream MJPEG em :8080/stream
"""

import RPi.GPIO as GPIO
import adafruit_dht
import board
import requests
import threading
import time
import cv2
import os
from datetime import datetime
from http.server import BaseHTTPRequestHandler, HTTPServer
from socketserver import ThreadingMixIn

# ── Configuração ──────────────────────────────────────────────────────────────

API_URL       = "https://SEU_SERVIDOR/api/api.php"
WEBCAM_SOURCE = "http://IP_DO_TELEMOVEL:4747/video"
API_KEY       = "SUA_API_KEY"
HEADERS       = {"X-API-Key": API_KEY}
STREAM_PORT   = 8080

# Session reutiliza a ligação TCP/TLS entre pedidos — mais rápido que requests.get/post direto
sessao = requests.Session()
sessao.headers.update(HEADERS)

PINO_DHT    = board.D4
PINO_PIR    = 17
PINO_CHOQUE = 27
PINO_BUZZER = 22

DIR_IMAGENS = os.path.join(os.path.dirname(__file__), "imagens")
os.makedirs(DIR_IMAGENS, exist_ok=True)

# ── GPIO setup ────────────────────────────────────────────────────────────────

GPIO.setmode(GPIO.BCM)
GPIO.setwarnings(False)
GPIO.setup(PINO_PIR,    GPIO.IN)
GPIO.setup(PINO_CHOQUE, GPIO.IN)
GPIO.setup(PINO_BUZZER, GPIO.OUT)

dht = adafruit_dht.DHT11(PINO_DHT)

# PWM no buzzer: frequência muda no thread_alarme para fazer o efeito de sirene
buzzer_pwm = GPIO.PWM(PINO_BUZZER, 2000)
buzzer_pwm.start(0)

# Flag partilhada entre o loop principal e o thread do buzzer
buzzer_ativo = False

# Frame da câmara partilhado entre o thread de captura e o servidor MJPEG
frame_atual = None
lock_frame  = threading.Lock()

# ── Auxiliares ────────────────────────────────────────────────────────────────

def post(nome, valor):
    try:
        r = sessao.post(API_URL, data={"nome": nome, "valor": valor}, timeout=5)
        print(f"[POST] {nome}={valor} → {r.status_code}")
    except Exception as e:
        print(f"[ERRO POST {nome}] {e}")

def get(nome):
    try:
        r = sessao.get(API_URL, params={"nome": nome}, timeout=5)
        if r.status_code == 200:
            return r.text.strip()
    except Exception as e:
        print(f"[ERRO GET {nome}] {e}")
    return None

def capturar_imagem(motivo="auto"):
    # Copia o frame dentro do lock mas faz o encode e upload fora — não bloqueia o stream
    global frame_atual
    with lock_frame:
        if frame_atual is None:
            print("[CAM] Sem frame disponível — câmara ainda não iniciada")
            return
        frame = frame_atual.copy()
    try:
        nome_ficheiro = f"{motivo}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.jpg"
        caminho = os.path.join(DIR_IMAGENS, nome_ficheiro)
        cv2.imwrite(caminho, frame)
        with open(caminho, "rb") as f:
            r = requests.post(
                API_URL.replace("api.php", "upload.php"),
                files={"imagem": (nome_ficheiro, f, "image/jpeg")},
                headers=HEADERS,
                timeout=10
            )
        print(f"[CAM] Upload {motivo} → {r.status_code}: {r.text}")
    except Exception as e:
        print(f"[ERRO CAM] {e}")

# ── Threads ───────────────────────────────────────────────────────────────────
# Apenas 3 threads — cada uma bloqueia indefinidamente e não pode ir para o loop principal:
#   thread_webcam:        cap.read() bloqueia à espera de frames
#   thread_stream:        serve_forever() bloqueia à espera de clientes HTTP
#   thread_alarme_buzzer: ciclo PWM contínuo com sleeps muito curtos

class ThreadingHTTPServer(ThreadingMixIn, HTTPServer):
    daemon_threads = True

class MJPEGHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path != '/stream':
            self.send_response(404)
            self.end_headers()
            return
        self.send_response(200)
        self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
        self.end_headers()
        try:
            while True:
                with lock_frame:
                    if frame_atual is None:
                        time.sleep(0.1)
                        continue
                    frame = frame_atual.copy()
                _, jpg = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
                self.wfile.write(b'--frame\r\nContent-Type: image/jpeg\r\n\r\n')
                self.wfile.write(jpg.tobytes())
                self.wfile.write(b'\r\n')
                time.sleep(0.05)  # ~20 fps
        except Exception:
            pass

    def log_message(self, format, *args):
        pass  # suprime logs HTTP no terminal

def thread_webcam():
    global frame_atual
    cap = None
    falhas = 0
    while True:
        try:
            if cap is None or not cap.isOpened():
                cap = cv2.VideoCapture(WEBCAM_SOURCE, cv2.CAP_FFMPEG)
                if not cap.isOpened():
                    print("[WEBCAM] Câmara indisponível, a tentar de novo em 5s...")
                    time.sleep(5)
                    continue
                print("[WEBCAM] Câmara iniciada")
                falhas = 0
            ret, frame = cap.read()
            if ret:
                with lock_frame:
                    frame_atual = frame
                falhas = 0
                time.sleep(0.033)  # ~30 fps na leitura
            else:
                falhas += 1
                if falhas >= 10:
                    # Só reconecta depois de 10 falhas seguidas para não ciclar em erro único
                    cap.release()
                    cap = None
                    falhas = 0
                    print("[WEBCAM] Stream perdido, a reconectar em 5s...")
                    time.sleep(5)
        except Exception as e:
            print(f"[ERRO WEBCAM] {e}")
            if cap:
                cap.release()
                cap = None
            time.sleep(5)

def thread_stream():
    server = ThreadingHTTPServer(('0.0.0.0', STREAM_PORT), MJPEGHandler)
    print(f"[STREAM] Câmara ao vivo disponível em http://[IP]:{STREAM_PORT}/stream")
    server.serve_forever()

def thread_alarme_buzzer():
    # Varre a frequência entre 400 e 1600 Hz para simular sirene
    # O sleep de 10ms por passo é curto demais para gerir no loop principal
    global buzzer_ativo
    while True:
        if not buzzer_ativo:
            buzzer_pwm.ChangeDutyCycle(0)
            time.sleep(0.05)
            continue
        buzzer_pwm.ChangeDutyCycle(50)
        for f in range(400, 1601, 10):
            if not buzzer_ativo: break
            buzzer_pwm.ChangeFrequency(f)
            time.sleep(0.010)
        for f in range(1600, 399, -10):
            if not buzzer_ativo: break
            buzzer_pwm.ChangeFrequency(f)
            time.sleep(0.010)

# ── Início ────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=== SecureNode — Raspberry Pi ===")
    print(f"[INICIO] {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    # Garante que sensores binários ficam a 0 no arranque (evita estados residuais)
    for sensor in ("pir", "choque", "buzzer"):
        post(sensor, 0)

    for alvo in (thread_webcam, thread_stream, thread_alarme_buzzer):
        threading.Thread(target=alvo, daemon=True).start()

    print("[OK] Threads ativos. A iniciar loop principal...\n")

    # ── Estado do loop principal ──────────────────────────────────────────────

    pir_anterior    = -1

    choque_anterior = 0
    choque_estado   = 0    # 0 = normal, 1 = aguarda reset após deteção
    choque_timer    = 0
    CHOQUE_DURACAO  = 3    # segundos que choque fica a 1 antes de resetar

    temp_anterior  = None
    hum_anterior   = None
    ultimo_dht     = 0

    fogo_anterior   = None
    som_anterior    = None
    buzzer_por_fogo = False  # distingue se o buzzer foi ligado por fogo ou pelo dashboard
    ultimo_polling  = 0

    try:
        while True:
            agora = time.time()

            # ── DHT11 (a cada 10s, só posta se mudou ≥ 0.5°C ou 1%)
            if agora - ultimo_dht >= 10:
                try:
                    temp = dht.temperature
                    hum  = dht.humidity
                    if temp is not None and hum is not None:
                        t = round(temp, 1)
                        h = round(hum, 1)
                        if temp_anterior is None or abs(t - temp_anterior) >= 0.5:
                            post("temperatura", t)
                            temp_anterior = t
                        if hum_anterior is None or abs(h - hum_anterior) >= 1:
                            post("humidade", h)
                            hum_anterior = h
                        print(f"[DHT] {t}°C  {h}%")
                except Exception as e:
                    # DHT11 falha leituras com frequência — é normal, tenta de novo a seguir
                    print(f"[ERRO DHT] {e}")
                ultimo_dht = agora

            # ── PIR — posta sempre que o estado muda
            estado_pir = GPIO.input(PINO_PIR)
            if estado_pir != pir_anterior:
                post("pir", estado_pir)
                if estado_pir == 1:
                    print("[PIR] Movimento detetado!")
                    post("rele", 1)
                    threading.Thread(target=capturar_imagem, args=("pir",), daemon=True).start()
                else:
                    print("[PIR] Sem movimento")
                    post("rele", 0)
                pir_anterior = estado_pir

            # ── Choque — state machine para evitar sleep bloqueante no loop
            leitura_choque = GPIO.input(PINO_CHOQUE)
            if choque_estado == 0:
                if leitura_choque != choque_anterior:
                    post("choque", 1)
                    post("rele", 1)
                    print("[CHOQUE] Choque/vibração detetado!")
                    threading.Thread(target=capturar_imagem, args=("choque",), daemon=True).start()
                    choque_timer = agora
                    choque_estado = 1
                choque_anterior = leitura_choque
            elif choque_estado == 1:
                if agora - choque_timer >= CHOQUE_DURACAO:
                    post("choque", 0)
                    post("rele", 0)
                    print("[CHOQUE] Normal")
                    choque_anterior = GPIO.input(PINO_CHOQUE)
                    choque_estado = 0

            # ── Polling da API (a cada 1s) — reage a fogo/som detetados pelo ESP32
            if agora - ultimo_polling >= 1:
                try:
                    buzzer_api = get("buzzer")
                    fogo_api   = get("fogo")
                    som_api    = get("som")

                    # Buzzer só fica ativo por fogo OU por comando direto do dashboard
                    # A flag buzzer_por_fogo evita confundir os dois casos ao desligar
                    deve_ativo = (fogo_api == "1") or (buzzer_api == "1" and not buzzer_por_fogo)
                    if deve_ativo:
                        buzzer_ativo = True
                        if fogo_api == "1" and not buzzer_por_fogo:
                            print("[POLLING] Fogo detetado pelo ESP32 — alarme ativado!")
                            post("buzzer", 1)
                            buzzer_por_fogo = True
                    else:
                        buzzer_ativo = False
                        if buzzer_por_fogo:
                            post("buzzer", 0)
                            buzzer_por_fogo = False

                    # Captura imagem na primeira deteção (transição 0→1)
                    if fogo_api == "1" and fogo_anterior != "1":
                        print("[POLLING] Fogo — a capturar imagem!")
                        threading.Thread(target=capturar_imagem, args=("fogo",), daemon=True).start()
                    if som_api == "1" and som_anterior != "1":
                        print("[POLLING] Som — a capturar imagem!")
                        threading.Thread(target=capturar_imagem, args=("som",), daemon=True).start()

                    fogo_anterior = fogo_api
                    som_anterior  = som_api
                except Exception as e:
                    print(f"[ERRO POLLING] {e}")
                ultimo_polling = agora
            time.sleep(0.05)  # loop a ~20 Hz

    except KeyboardInterrupt:
        print("\n[SAÍDA] A terminar...")
        GPIO.cleanup()
