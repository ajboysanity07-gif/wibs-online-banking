import { Minus, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Cropper, { type Area, type Point } from 'react-easy-crop';
import 'react-easy-crop/react-easy-crop.css';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const MIN_ZOOM = 1;
const MAX_ZOOM = 3;
const ZOOM_STEP = 0.1;
const CROP_AREA_INSET = 16;

export type ProfileImageCropResult = {
    crop: Point;
    zoom: number;
    croppedAreaPixels: Area | null;
};

type Props = {
    isOpen: boolean;
    onClose: () => void;
    imagePreviewUrl: string | null;
    onSave?: (result: ProfileImageCropResult) => Promise<void> | void;
};

export default function ProfileImageCropModal({
    isOpen,
    onClose,
    imagePreviewUrl,
    onSave,
}: Props) {
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState<number>(MIN_ZOOM);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(
        null,
    );
    const [cropAreaSize, setCropAreaSize] = useState<number | null>(null);
    const cropContainerRef = useRef<HTMLDivElement | null>(null);
    const hasPreview = Boolean(imagePreviewUrl);

    const resetCropState = useCallback(() => {
        setCrop({ x: 0, y: 0 });
        setZoom(MIN_ZOOM);
        setCroppedAreaPixels(null);
    }, []);

    const handleCropComplete = useCallback((_: Area, croppedPixels: Area) => {
        setCroppedAreaPixels(croppedPixels);
    }, []);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const container = cropContainerRef.current;

        if (!container) {
            return;
        }

        const updateSize = () => {
            const { width, height } = container.getBoundingClientRect();
            const size = Math.max(
                0,
                Math.floor(Math.min(width, height) - CROP_AREA_INSET * 2),
            );

            setCropAreaSize(size > 0 ? size : null);
        };

        updateSize();
        requestAnimationFrame(updateSize);
        setTimeout(updateSize, 60);

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(updateSize);
        observer.observe(container);

        return () => {
            observer.disconnect();
        };
    }, [isOpen]);

    const clampZoom = (value: number) =>
        Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value));

    const handleZoomChange = (value: number) => {
        setZoom(clampZoom(value));
    };

    const handleSave = useCallback(async () => {
        if (!imagePreviewUrl || !croppedAreaPixels) {
            return;
        }

        try {
            await onSave?.({
                crop,
                zoom,
                croppedAreaPixels,
            });
            onClose();
        } catch {
            return;
        }
    }, [crop, croppedAreaPixels, imagePreviewUrl, onClose, onSave, zoom]);

    const canSave = hasPreview && Boolean(croppedAreaPixels);
    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                    return;
                }

                resetCropState();
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Crop profile photo</DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    <div className="mx-auto w-full max-w-[520px]">
                        <div
                            ref={cropContainerRef}
                            className="relative aspect-square w-full max-w-[520px] overflow-hidden rounded-xl border border-border bg-muted"
                        >
                            {hasPreview ? (
                                <Cropper
                                    image={imagePreviewUrl ?? undefined}
                                    crop={crop}
                                    zoom={zoom}
                                    rotation={0}
                                    aspect={1}
                                    cropSize={
                                        cropAreaSize
                                            ? {
                                                  width: cropAreaSize,
                                                  height: cropAreaSize,
                                              }
                                            : undefined
                                    }
                                    minZoom={MIN_ZOOM}
                                    maxZoom={MAX_ZOOM}
                                    cropShape="round"
                                    showGrid={false}
                                    zoomSpeed={0.1}
                                    restrictPosition
                                    onCropChange={setCrop}
                                    onZoomChange={handleZoomChange}
                                    onCropComplete={handleCropComplete}
                                    classes={{
                                        containerClassName:
                                            'bg-muted/70 dark:bg-muted/60',
                                        cropAreaClassName:
                                            '!border-0 !shadow-none',
                                    }}
                                    style={{}}
                                    mediaProps={{
                                        alt: 'Profile photo preview',
                                    }}
                                    cropperProps={{
                                        tabIndex: 0,
                                        'aria-label': 'Crop area',
                                        className:
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
                                    }}
                                    keyboardStep={0.1}
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                    No image selected.
                                </div>
                            )}
                            {hasPreview ? (
                                <div className="pointer-events-none absolute inset-0 z-20">
                                    <div
                                        className="absolute rounded-full border-[4px] border-white/95 shadow-[0_0_0_9999px_rgba(0,0,0,0.72),0_0_0_2px_rgba(0,0,0,0.85),0_0_18px_rgba(0,0,0,0.7)] dark:border-white/85 dark:shadow-[0_0_0_9999px_rgba(0,0,0,0.84),0_0_0_2px_rgba(0,0,0,0.92),0_0_18px_rgba(0,0,0,0.85)]"
                                        style={{
                                            inset: CROP_AREA_INSET,
                                        }}
                                    />
                                    <div
                                        className="absolute rounded-full border border-white/45 dark:border-white/35"
                                        style={{
                                            inset: CROP_AREA_INSET + 12,
                                        }}
                                    />
                                </div>
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Zoom
                        </p>
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() =>
                                    handleZoomChange(zoom - ZOOM_STEP)
                                }
                                disabled={!hasPreview}
                            >
                                <Minus />
                                <span className="sr-only">Zoom out</span>
                            </Button>
                            <input
                                aria-label="Zoom"
                                type="range"
                                min={MIN_ZOOM}
                                max={MAX_ZOOM}
                                step={0.01}
                                value={zoom}
                                onChange={(event) =>
                                    handleZoomChange(Number(event.target.value))
                                }
                                disabled={!hasPreview}
                                className="h-2 w-full min-w-0 cursor-pointer appearance-none rounded-full bg-muted accent-primary focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() =>
                                    handleZoomChange(zoom + ZOOM_STEP)
                                }
                                disabled={!hasPreview}
                            >
                                <Plus />
                                <span className="sr-only">Zoom in</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter className="flex-col gap-3 sm:flex-row sm:gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSave}
                        disabled={!canSave}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
