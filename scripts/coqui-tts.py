#!/usr/bin/env python3
"""Coqui TTS local — requer: pip install TTS"""
import argparse
import sys

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--text-file', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--language', default='pt')
    args = parser.parse_args()

    with open(args.text_file, 'r', encoding='utf-8') as f:
        text = f.read().strip()

    if not text:
        print('Texto vazio', file=sys.stderr)
        sys.exit(1)

    try:
        from TTS.api import TTS
    except ImportError:
        print('Pacote TTS não instalado. Execute: pip install TTS', file=sys.stderr)
        sys.exit(1)

    tts = TTS(model_name='tts_models/multilingual/multi-dataset/xtts_v2', progress_bar=False, gpu=False)
    tts.tts_to_file(text=text, file_path=args.output, language=args.language)

if __name__ == '__main__':
    main()
