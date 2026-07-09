#!/usr/bin/env python3
"""Fallback Edge TTS via pip: pip install edge-tts"""
import argparse
import asyncio
import sys


async def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--voice', default='pt-BR-FranciscaNeural')
    parser.add_argument('--input', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()

    with open(args.input, 'r', encoding='utf-8') as f:
        text = f.read().strip()

    if not text:
        print('Texto vazio', file=sys.stderr)
        sys.exit(1)

    try:
        import edge_tts
    except ImportError:
        print('Pacote edge-tts não instalado. Execute: pip install edge-tts', file=sys.stderr)
        sys.exit(1)

    communicate = edge_tts.Communicate(text, args.voice)
    await communicate.save(args.output)


if __name__ == '__main__':
    asyncio.run(main())
