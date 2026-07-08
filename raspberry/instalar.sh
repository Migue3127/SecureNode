#!/bin/bash
# Script de instalação — SecureNode Raspberry Pi
# Raspberry Pi OS 32-bit (Bookworm)

echo "=== SecureNode — Instalação ==="

# Atualiza o sistema
sudo apt update && sudo apt upgrade -y

# Instala dependências do sistema
sudo apt install -y \
    python3-pip \
    python3-opencv \
    libgpiod2 \
    git

# Instala bibliotecas Python
# --break-system-packages necessário no Bookworm (PEP 668)
pip3 install --break-system-packages \
    RPi.GPIO \
    adafruit-circuitpython-dht \
    requests

echo ""
echo "=== Instalação concluída ==="
echo "Para iniciar: python3 vigilancia.py"
