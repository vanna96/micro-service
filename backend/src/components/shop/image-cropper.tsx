import React, { useState, useCallback } from "react";
import { createPortal } from "react-dom";
import Cropper from "react-easy-crop";
import type { Area } from "react-easy-crop";
import "react-easy-crop/react-easy-crop.css";

const createImage = (url: string): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener("load", () => resolve(image));
        image.addEventListener("error", (error) => reject(error));
        image.setAttribute("crossOrigin", "anonymous");
        image.src = url;
    });

async function getCroppedImg(imageSrc: string, pixelCrop: Area): Promise<File> {
    const image = await createImage(imageSrc);
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    if (!ctx) {
        throw new Error("No 2d context");
    }

    // Mobile camera photos can be several thousand pixels wide. Uploading the
    // full-resolution crop as PNG can exceed PHP's multipart limit even though
    // the image itself looked valid in the browser. The matcher does not need
    // that resolution, so keep the longest edge compact. PNG is intentional:
    // the deployed GD build supports it consistently across every app worker.
    const maxDimension = 1200;
    const scale = Math.min(
        1,
        maxDimension / Math.max(pixelCrop.width, pixelCrop.height),
    );
    canvas.width = Math.max(1, Math.round(pixelCrop.width * scale));
    canvas.height = Math.max(1, Math.round(pixelCrop.height * scale));

    // Fill white background in case of transparent png
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (file) => {
                if (file) {
                    resolve(
                        new File([file], "cropped.png", { type: "image/png" }),
                    );
                } else {
                    reject(new Error("Canvas is empty"));
                }
            },
            "image/png",
        );
    });
}

export function ImageCropModal({
    imageSrc,
    onClose,
    onCropComplete,
}: {
    imageSrc: string;
    onClose: () => void;
    onCropComplete: (croppedFile: File) => void;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(
        null,
    );
    const [isCropping, setIsCropping] = useState(false);

    const onCropCompleteHandler = useCallback(
        (_croppedArea: Area, croppedAreaPixels: Area) => {
            setCroppedAreaPixels(croppedAreaPixels);
        },
        [],
    );

    const handleCrop = async () => {
        if (!croppedAreaPixels) return;
        setIsCropping(true);
        try {
            const croppedImage = await getCroppedImg(
                imageSrc,
                croppedAreaPixels,
            );
            onCropComplete(croppedImage);
        } catch (e) {
            console.error(e);
        } finally {
            setIsCropping(false);
        }
    };

    const modalContent = (
        <div
            style={{
                position: "fixed",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                zIndex: 2147483647,
                background: "#0f172a",
                display: "flex",
                flexDirection: "column",
            }}
        >
            <div style={{ position: "relative", flex: 1 }}>
                <Cropper
                    image={imageSrc}
                    crop={crop}
                    zoom={zoom}
                    aspect={1}
                    onCropChange={setCrop}
                    onZoomChange={setZoom}
                    onCropComplete={onCropCompleteHandler}
                />
            </div>
            <div
                style={{
                    padding: "24px 16px",
                    display: "flex",
                    justifyContent: "space-between",
                    background: "#0f172a",
                    paddingBottom: "120px", // Increased padding to clear bottom navigation and iOS safari bar
                }}
            >
                <button
                    onClick={onClose}
                    disabled={isCropping}
                    style={{
                        padding: "12px 24px",
                        background: "#334155",
                        color: "#fff",
                        borderRadius: "12px",
                        border: "none",
                        fontWeight: 600,
                        fontSize: "15px",
                        cursor: "pointer",
                    }}
                >
                    Cancel
                </button>
                <button
                    onClick={handleCrop}
                    disabled={isCropping}
                    style={{
                        padding: "12px 24px",
                        background: "#ff762d",
                        color: "#fff",
                        borderRadius: "12px",
                        border: "none",
                        fontWeight: 600,
                        fontSize: "15px",
                        cursor: "pointer",
                    }}
                >
                    {isCropping ? "Processing..." : "Search Selected Area"}
                </button>
            </div>
        </div>
    );

    if (typeof document !== "undefined") {
        return createPortal(modalContent, document.body);
    }

    return null;
}
