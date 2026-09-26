"""Private, localhost-only Chatterbox studio service for Avatar IA."""

import base64
import logging
import os
import shutil
import subprocess
import tempfile
from pathlib import Path

import torch
import torchaudio
from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="Avatar IA local Voicebox", docs_url=None, redoc_url=None)
logger = logging.getLogger("avatar_voicebox")
MODEL = None
TOKEN = os.environ.get("VOICEBOX_LOCAL_TOKEN", "")
FFMPEG = os.environ.get("FFMPEG_BINARY") or shutil.which("ffmpeg") or "/opt/homebrew/bin/ffmpeg"


def authorize(authorization: str | None = Header(default=None)) -> None:
    if not TOKEN or authorization != f"Bearer {TOKEN}":
        raise HTTPException(status_code=401, detail="Unauthorized")


def device() -> str:
    if torch.backends.mps.is_available():
        return "mps"
    if torch.cuda.is_available():
        return "cuda"
    return "cpu"


def model():
    global MODEL
    if MODEL is None:
        from chatterbox.mtl_tts import ChatterboxMultilingualTTS

        MODEL = ChatterboxMultilingualTTS.from_pretrained(device=device())
    return MODEL


def timed_words(text: str, duration_ms: int) -> list[dict[str, int | str]]:
    words = text.split()
    if not words:
        return []
    result: list[dict[str, int | str]] = []
    for index, word in enumerate(words):
        start = round(duration_ms * index / len(words))
        end = round(duration_ms * (index + 1) / len(words))
        result.append({"word": word, "start_ms": start, "end_ms": end})
    return result


@app.get("/health")
def health() -> dict[str, bool | str]:
    return {"status": "ok", "models_loaded": MODEL is not None, "language": "es"}


@app.post("/v1/cloned/speech")
async def cloned_speech(
    input: str = Form(..., min_length=1, max_length=600),
    voice_mode: str = Form("cloned"),
    language: str = Form("es"),
    locale: str = Form("es-PE"),
    response_format: str = Form("mp3"),
    voice_sample: UploadFile = File(...),
    _: None = Depends(authorize),
) -> JSONResponse:
    if voice_mode not in {"cloned", "synthetic"} or language != "es" or locale != "es-PE" or response_format != "mp3":
        raise HTTPException(status_code=422, detail="Solo se admite clonación en español peruano a MP3.")

    with tempfile.TemporaryDirectory(prefix="avatar-voicebox-") as directory:
        root = Path(directory)
        sample_path = root / "reference.wav"
        sample_path.write_bytes(await voice_sample.read())
        wav_path = root / "speech.wav"
        mp3_path = root / "speech.mp3"

        try:
            waveform = model().generate(input, language_id="es", audio_prompt_path=str(sample_path))
            torchaudio.save(str(wav_path), waveform.cpu(), model().sr)
            subprocess.run(
                [FFMPEG, "-y", "-i", str(wav_path), "-codec:a", "libmp3lame", "-b:a", "192k", str(mp3_path)],
                check=True,
                capture_output=True,
                timeout=120,
            )
        except Exception as error:
            logger.exception("Chatterbox could not synthesize cloned speech")
            raise HTTPException(status_code=503, detail="No se pudo sintetizar la prueba local.") from error

        audio = mp3_path.read_bytes()
        duration_ms = round(waveform.shape[-1] / model().sr * 1000)
        return JSONResponse(
            {
                "audio_base64": base64.b64encode(audio).decode("ascii"),
                "duration_ms": duration_ms,
                "words": timed_words(input, duration_ms),
            }
        )


@app.post("/v1/anita/speech")
async def synthetic_speech(
    payload: dict,
    _: None = Depends(authorize),
) -> JSONResponse:
    text = payload.get("input")
    if not isinstance(text, str) or not text.strip() or len(text) > 600:
        raise HTTPException(status_code=422, detail="El texto de síntesis no es válido.")

    with tempfile.TemporaryDirectory(prefix="avatar-voicebox-") as directory:
        root = Path(directory)
        wav_path = root / "speech.wav"
        mp3_path = root / "speech.mp3"
        try:
            waveform = model().generate(text, language_id="es")
            torchaudio.save(str(wav_path), waveform.cpu(), model().sr)
            subprocess.run(
                [FFMPEG, "-y", "-i", str(wav_path), "-codec:a", "libmp3lame", "-b:a", "192k", str(mp3_path)],
                check=True,
                capture_output=True,
                timeout=120,
            )
        except Exception as error:
            logger.exception("Chatterbox could not synthesize synthetic speech")
            raise HTTPException(status_code=503, detail="No se pudo sintetizar la prueba local.") from error

        audio = mp3_path.read_bytes()
        duration_ms = round(waveform.shape[-1] / model().sr * 1000)
        return JSONResponse(
            {
                "audio_base64": base64.b64encode(audio).decode("ascii"),
                "duration_ms": duration_ms,
                "words": timed_words(text, duration_ms),
            }
        )
