import math
import os
import re
import tempfile
from io import BytesIO
from functools import lru_cache
from pathlib import Path
from typing import Optional

from fastapi import APIRouter, File, Form, HTTPException, UploadFile
from fastapi.responses import Response
from fastembed import ImageEmbedding
from PIL import Image, UnidentifiedImageError
from pydantic import BaseModel
from rapidocr import RapidOCR
import faiss
import numpy as np
from sqlalchemy import create_engine, text

router = APIRouter()

MODEL_NAME = os.getenv("VISION_EMBEDDING_MODEL", "google/siglip2-base-patch16-224")
MODEL_CACHE = os.getenv("FASTEMBED_CACHE_PATH", "/models")
MAX_IMAGES = 8
MAX_IMAGE_BYTES = 8 * 1024 * 1024


@lru_cache(maxsize=1)
def get_model() -> ImageEmbedding:
    """Load the ONNX model once per API worker."""
    return ImageEmbedding(model_name=MODEL_NAME, cache_dir=MODEL_CACHE)


@lru_cache(maxsize=1)
def get_ocr() -> RapidOCR:
    """Load the OCR detector once; its bundled ONNX models run on the same CPU runtime."""
    return RapidOCR()


def get_tenant_db_engine(tenant_name: str = "rechna"):
    """Get SQLAlchemy engine for the specified tenant MySQL database."""
    base_url = os.getenv("DATABASE_URL")
    if not base_url:
        raise HTTPException(status_code=500, detail="DATABASE_URL environment variable is not set.")
    prefix, _ = base_url.rsplit("/", 1)
    safe_tenant = "".join(c for c in tenant_name if c.isalnum() or c in ("_", "-"))
    if not safe_tenant:
        safe_tenant = "rechna"
    return create_engine(f"{prefix}/{safe_tenant}")


def extract_candidate_skus(text_content: str) -> list[str]:
    """Extract candidate SKUs and item codes from OCR text."""
    patterns = [
        r"\b[A-Za-z0-9]{2,}(?:[-_][A-Za-z0-9]+)+\b",  # e.g. FD-DRY-005, WT-SMT-002, EL-CHR-001
        r"\b[A-Za-z]{2,}\d{2,}\b",                     # e.g. FD005, BCH033
        r"\b\d{3,}\b",                                  # e.g. 001, 002
    ]
    candidates: set[str] = set()
    for pattern in patterns:
        for match in re.finditer(pattern, text_content):
            token = match.group(0).upper().strip()
            if len(token) >= 3:
                candidates.add(token)
    return sorted(candidates, key=len, reverse=True)


def normalize_sku(value: str) -> str:
    """Normalize SKU string for robust comparison."""
    return re.sub(r"[^A-Z0-9]+", "", value.upper())


def extract_words(value: str) -> set[str]:
    """Extract alphanumeric words of length >= 3."""
    return set(re.findall(r"[A-Za-z0-9]{3,}", value.upper()))


def run_ocr(image_path: str) -> dict:
    """Run RapidOCR and extract structured text, scores, boxes, and SKUs."""
    try:
        ocr_result = get_ocr()(image_path)
    except Exception:
        return {
            "text": "",
            "texts": [],
            "candidate_skus": [],
            "candidate_words": [],
            "lines": [],
        }

    txts_val = getattr(ocr_result, "txts", None)
    raw_txts = txts_val if txts_val is not None else ()

    scores_val = getattr(ocr_result, "scores", None)
    raw_scores = scores_val if scores_val is not None else ()

    boxes_val = getattr(ocr_result, "boxes", None)
    raw_boxes = boxes_val if boxes_val is not None else ()

    texts: list[str] = []
    lines: list[dict] = []

    for i, t in enumerate(raw_txts):
        if not t:
            continue
        text_str = str(t).strip()
        if not text_str:
            continue
        texts.append(text_str)
        score = float(raw_scores[i]) if i < len(raw_scores) and raw_scores[i] is not None else 1.0
        box = raw_boxes[i].tolist() if i < len(raw_boxes) and hasattr(raw_boxes[i], "tolist") else None
        lines.append({
            "text": text_str,
            "score": round(score, 4),
            "box": box,
        })

    full_text = " ".join(texts)
    skus = extract_candidate_skus(full_text)
    words = sorted(extract_words(full_text))

    return {
        "text": full_text,
        "texts": texts,
        "candidate_skus": skus,
        "candidate_words": words,
        "lines": lines,
    }


def search_products_by_ocr_data(
    ocr_data: dict,
    tenant: str = "rechna",
    limit: int = 20,
) -> list[dict]:
    """Query tenant items and match against OCR candidate SKUs and text."""
    engine = get_tenant_db_engine(tenant)
    candidate_skus = ocr_data.get("candidate_skus", [])
    ocr_words = set(ocr_data.get("candidate_words", []))
    full_text_norm = normalize_sku(ocr_data.get("text", "")).replace("O", "0")

    query = text("""
        SELECT i.id, i.sku, i.name, i.foreign_name, i.price, i.status,
               g.name AS image_name
        FROM items i
        LEFT JOIN galleries g ON i.image_id = g.id
        WHERE i.status = 'Active'
    """)

    with engine.connect() as conn:
        rows = conn.execute(query).fetchall()

    sku_matches = []
    name_matches = []

    for row in rows:
        item_id = int(row.id)
        sku = str(row.sku or "")
        name = str(row.name or "")
        foreign_name = str(row.foreign_name or "")
        price = float(row.price) if row.price is not None else 0.0
        image_name = str(row.image_name or "")

        norm_item_sku = normalize_sku(sku)
        norm_item_sku_zero = norm_item_sku.replace("O", "0")

        # 1. Exact or normalized SKU match
        is_sku_match = False
        if len(norm_item_sku) >= 3:
            for candidate in candidate_skus:
                norm_cand = normalize_sku(candidate).replace("O", "0")
                if norm_item_sku_zero == norm_cand:
                    is_sku_match = True
                    break
            if not is_sku_match and (norm_item_sku_zero in full_text_norm):
                is_sku_match = True

        if is_sku_match:
            sku_matches.append({
                "id": item_id,
                "sku": sku,
                "name": name,
                "foreign_name": foreign_name,
                "price": price,
                "image_name": image_name,
                "match_type": "ocr_sku",
                "similarity_score": 1.0,
                "distance": 0,
            })
            continue

        # 2. Name token overlap match
        item_words = extract_words(name)
        if foreign_name:
            item_words |= extract_words(foreign_name)

        if item_words and ocr_words:
            overlap = ocr_words.intersection(item_words)
            if overlap:
                overlap_ratio = len(overlap) / max(1, len(item_words))
                if len(overlap) >= 2 or overlap_ratio >= 0.5:
                    score = min(0.99, round(0.70 + 0.25 * overlap_ratio, 4))
                    dist = int(round((1 - score) * 64))
                    name_matches.append({
                        "id": item_id,
                        "sku": sku,
                        "name": name,
                        "foreign_name": foreign_name,
                        "price": price,
                        "image_name": image_name,
                        "match_type": "ocr_name",
                        "similarity_score": score,
                        "distance": dist,
                        "matched_words": list(overlap),
                    })

    name_matches.sort(key=lambda m: m["similarity_score"], reverse=True)
    results = sku_matches + name_matches
    return results[:limit]


@router.post("/ocr")
def ocr_endpoint(
    image: UploadFile = File(...),
):
    """Perform OCR on an uploaded image and extract text, lines, bounding boxes, and candidate SKUs."""
    contents = image.file.read(MAX_IMAGE_BYTES + 1)
    if not contents or len(contents) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=422, detail="Image must be 8 MB or smaller.")

    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
        tmp_path = tmp.name
        try:
            with Image.open(BytesIO(contents)) as source:
                source.convert("RGB").save(tmp_path, "JPEG", quality=92)
        except (UnidentifiedImageError, OSError, ValueError) as exc:
            raise HTTPException(status_code=422, detail="Uploaded file is not a valid image.") from exc

    try:
        result = run_ocr(tmp_path)
    finally:
        if os.path.exists(tmp_path):
            os.remove(tmp_path)

    return {
        "success": True,
        "filename": image.filename,
        **result,
    }


@router.post("/products/search-by-ocr")
def search_products_by_ocr_endpoint(
    image: UploadFile = File(...),
    tenant: str = Form("rechna"),
    limit: int = Form(20),
):
    """Search products in tenant database by extracting text & SKUs via OCR from the uploaded image."""
    contents = image.file.read(MAX_IMAGE_BYTES + 1)
    if not contents or len(contents) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=422, detail="Image must be 8 MB or smaller.")

    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
        tmp_path = tmp.name
        try:
            with Image.open(BytesIO(contents)) as source:
                source.convert("RGB").save(tmp_path, "JPEG", quality=92)
        except (UnidentifiedImageError, OSError, ValueError) as exc:
            raise HTTPException(status_code=422, detail="Uploaded file is not a valid image.") from exc

    try:
        ocr_data = run_ocr(tmp_path)
        try:
            matched_products = search_products_by_ocr_data(ocr_data, tenant=tenant, limit=limit)
        except Exception as db_err:
            matched_products = []
    finally:
        if os.path.exists(tmp_path):
            os.remove(tmp_path)

    return {
        "success": True,
        "total": len(matched_products),
        "ocr": {
            "text": ocr_data["text"],
            "candidate_skus": ocr_data["candidate_skus"],
            "candidate_words": ocr_data["candidate_words"],
            "line_count": len(ocr_data["lines"]),
        },
        "data": matched_products,
    }


@router.post("/products/search-by-image")
def search_products_by_image_endpoint(
    image: UploadFile = File(...),
    tenant: str = Form("rechna"),
    limit: int = Form(20),
):
    """Search products by image OCR."""
    return search_products_by_ocr_endpoint(image=image, tenant=tenant, limit=limit)


@router.post("/convert-to-png")
def convert_to_png_endpoint(
    image: UploadFile = File(...),
):
    """Convert any uploaded image (JPEG, WebP, etc.) to PNG format for downstream consumers like PHP GD."""
    contents = image.file.read(MAX_IMAGE_BYTES + 1)
    if not contents or len(contents) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=422, detail="Image must be 8 MB or smaller.")

    try:
        with Image.open(BytesIO(contents)) as source:
            output = BytesIO()
            source.convert("RGB").save(output, format="PNG")
            return Response(content=output.getvalue(), media_type="image/png")
    except (UnidentifiedImageError, OSError, ValueError) as exc:
        raise HTTPException(status_code=422, detail="Uploaded file is not a valid image.") from exc


@router.post("/image-embeddings")
def image_embeddings(
    images: list[UploadFile] = File(...),
    include_ocr: bool = Form(False),
):
    """Compute normalized UNICOM vision embeddings and optionally extract OCR text."""
    if not images or len(images) > MAX_IMAGES:
        raise HTTPException(status_code=422, detail=f"Upload between 1 and {MAX_IMAGES} images.")

    with tempfile.TemporaryDirectory(prefix="vision-embedding-") as directory:
        paths: list[str] = []

        for index, upload in enumerate(images):
            contents = upload.file.read(MAX_IMAGE_BYTES + 1)
            if not contents or len(contents) > MAX_IMAGE_BYTES:
                raise HTTPException(status_code=422, detail="Each image must be 8 MB or smaller.")

            path = Path(directory) / f"image-{index}.jpg"
            try:
                with Image.open(BytesIO(contents)) as source:
                    source.convert("RGB").save(path, "JPEG", quality=92)
            except (UnidentifiedImageError, OSError, ValueError) as exception:
                raise HTTPException(status_code=422, detail="An uploaded file is not a valid image.") from exception
            paths.append(str(path))

        try:
            vectors = list(get_model().embed(paths, batch_size=min(4, len(paths))))
        except Exception as exception:
            raise HTTPException(status_code=503, detail="The vision model is unavailable.") from exception

        texts: list[str] = []
        candidate_skus: list[str] = []
        ocr_text = ""
        lines: list[dict] = []

        if include_ocr and paths:
            ocr_info = run_ocr(paths[0])
            texts = ocr_info["texts"]
            candidate_skus = ocr_info["candidate_skus"]
            ocr_text = ocr_info["text"]
            lines = ocr_info["lines"]

    normalized = []
    for vector in vectors:
        values = [float(value) for value in vector]
        magnitude = math.sqrt(sum(value * value for value in values))
        normalized.append([value / magnitude for value in values] if magnitude else values)

    return {
        "model": MODEL_NAME,
        "dimension": len(normalized[0]) if normalized else 0,
        "embeddings": normalized,
        "texts": texts,
        "ocr_text": ocr_text,
        "candidate_skus": candidate_skus,
        "lines": lines,
    }


class Candidate(BaseModel):
    id: str
    embedding: list[float]

class SearchRequest(BaseModel):
    target_embedding: list[float]
    candidate_embeddings: list[Candidate]
    top_k: int = 50

@router.post("/search-embeddings")
def search_embeddings(request: SearchRequest):
    if not request.candidate_embeddings or not request.target_embedding:
        return {"matches": []}

    dimension = len(request.target_embedding)

    target = np.array([request.target_embedding], dtype=np.float32)

    ids = []
    matrix = []
    for cand in request.candidate_embeddings:
        if len(cand.embedding) == dimension:
            ids.append(cand.id)
            matrix.append(cand.embedding)

    if not matrix:
        return {"matches": []}

    matrix_np = np.array(matrix, dtype=np.float32)

    # We use inner product (cosine similarity) because embeddings are normalized
    index = faiss.IndexFlatIP(dimension)
    index.add(matrix_np)

    k = min(request.top_k, len(ids))
    distances, indices = index.search(target, k)

    matches = []
    for dist, idx in zip(distances[0], indices[0]):
        if idx != -1:
            matches.append({
                "id": ids[idx],
                "similarity": float(dist)
            })

    return {"matches": matches}
