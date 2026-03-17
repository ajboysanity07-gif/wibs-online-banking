import { Minus, Plus } from 'lucide-react';
import { useCallback, useState } from 'react';
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
    const hasPreview = Boolean(imagePreviewUrl);

    const resetCropState = useCallback(() => {
        setCrop({ x: 0, y: 0 });
        setZoom(MIN_ZOOM);
        setCroppedAreaPixels(null);
    }, []);

    const handleCropComplete = useCallback(
        (_: Area, croppedPixels: Area) => {
            setCroppedAreaPixels(croppedPixels);
        },
        [],
    );

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
    }, [
        crop,
        croppedAreaPixels,
        imagePreviewUrl,
        onClose,
        onSave,
        zoom,
    ]);

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
                        <div className="relative h-[clamp(240px,50vh,520px)] w-full overflow-hidden rounded-xl border border-border bg-muted">
                            {hasPreview ? (
                                <Cropper
                                    image={imagePreviewUrl ?? undefined}
                                    crop={crop}
                                    zoom={zoom}
                                    rotation={0}
                                    aspect={1}
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
                                            'bg-muted/80 dark:bg-muted/60',
                                        cropAreaClassName:
                                            '!border-white/70 !shadow-[0_0_0_9999px_rgba(0,0,0,0.45)] dark:!border-white/50 dark:!shadow-[0_0_0_9999px_rgba(0,0,0,0.7)]',
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
                                    handleZoomChange(
                                        Number(event.target.value),
                                    )
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
