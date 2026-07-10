#!/usr/bin/env python3
"""Remove fundo de imagem via rembg (U²-Net). Uso: python remove-background.py input.png output.png"""
import sys


def main() -> int:
    if len(sys.argv) < 3:
        print("Uso: remove-background.py <entrada> <saida.png>", file=sys.stderr)
        return 2

    src, dst = sys.argv[1], sys.argv[2]

    try:
        from rembg import remove
        from PIL import Image
    except ImportError:
        print(
            "rembg não instalado. Rode: pip install rembg pillow",
            file=sys.stderr,
        )
        return 1

    try:
        with open(src, "rb") as f:
            data = f.read()
        result = remove(data)
        with open(dst, "wb") as f:
            f.write(result)
        return 0
    except Exception as exc:
        print(f"Erro ao remover fundo: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
