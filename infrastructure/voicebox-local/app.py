"""Private, localhost-only Chatterbox studio service for Avatar IA."""

import base64
import logging
import os
import shutil
import subprocess
import tempfile
import threading
from pathlib import Path

import torch
import torchaudio
from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

app = FastAPI(title="Avatar IA local Voicebox", docs_url=None, redoc_url=None)
logger = logging.getLogger("avatar_voicebox")
MODEL = None
GENERATION_LOCK = threading.Lock()
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
        result.append({"text": word, "start_ms": start, "end_ms": end})
    return result


def encode_mp3(waveform: torch.Tensor, sample_rate: int, mp3_path: Path) -> None:
    with tempfile.NamedTemporaryFile(prefix="avatar-voicebox-", suffix=".wav", delete=False) as wav_file:
        wav_path = Path(wav_file.name)

    try:
        torchaudio.save(str(wav_path), waveform.cpu(), sample_rate)
        subprocess.run(
            [FFMPEG, "-y", "-i", str(wav_path), "-codec:a", "libmp3lame", "-b:a", "192k", str(mp3_path)],
            check=True,
            capture_output=True,
            timeout=120,
        )
    finally:
        wav_path.unlink(missing_ok=True)


def synthesize_cloned_audio(text: str, sample: bytes) -> dict[str, int | str | list[dict[str, int | str]]]:
    with GENERATION_LOCK, tempfile.TemporaryDirectory(prefix="avatar-voicebox-") as directory:
        root = Path(directory)
        sample_path = root / "reference.wav"
        sample_path.write_bytes(sample)
        mp3_path = root / "speech.mp3"
        voice_model = model()
        waveform = voice_model.generate(text, language_id="es", audio_prompt_path=str(sample_path))
        encode_mp3(waveform, voice_model.sr, mp3_path)
        duration_ms = round(waveform.shape[-1] / voice_model.sr * 1000)

        return {
            "audio_base64": base64.b64encode(mp3_path.read_bytes()).decode("ascii"),
            "duration_ms": duration_ms,
            "words": timed_words(text, duration_ms),
        }


def synthesize_synthetic_audio(text: str) -> dict[str, int | str | list[dict[str, int | str]]]:
    with GENERATION_LOCK, tempfile.TemporaryDirectory(prefix="avatar-voicebox-") as directory:
        mp3_path = Path(directory) / "speech.mp3"
        voice_model = model()
        waveform = voice_model.generate(text, language_id="es")
        encode_mp3(waveform, voice_model.sr, mp3_path)
        duration_ms = round(waveform.shape[-1] / voice_model.sr * 1000)

        return {
            "audio_base64": base64.b64encode(mp3_path.read_bytes()).decode("ascii"),
            "duration_ms": duration_ms,
            "words": timed_words(text, duration_ms),
        }


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

    try:
        payload = await run_in_threadpool(synthesize_cloned_audio, input, await voice_sample.read())
    except Exception as error:
        logger.exception("Chatterbox could not synthesize cloned speech")
        raise HTTPException(status_code=503, detail="No se pudo sintetizar la prueba local.") from error

    return JSONResponse(payload)


@app.post("/v1/anita/speech")
async def synthetic_speech(
    payload: dict,
    _: None = Depends(authorize),
) -> JSONResponse:
    text = payload.get("input")
    if not isinstance(text, str) or not text.strip() or len(text) > 600:
        raise HTTPException(status_code=422, detail="El texto de síntesis no es válido.")

    try:
        payload = await run_in_threadpool(synthesize_synthetic_audio, text)
    except Exception as error:
        logger.exception("Chatterbox could not synthesize synthetic speech")
        raise HTTPException(status_code=503, detail="No se pudo sintetizar la prueba local.") from error

    return JSONResponse(payload)
