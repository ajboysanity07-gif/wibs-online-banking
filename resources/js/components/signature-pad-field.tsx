import { useEffect, useRef, useState } from 'react';
import type ReactSignatureCanvas from 'react-signature-canvas';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type SignaturePadFieldProps = {
    name: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
    value?: string | null;
    onChange?: (value: string) => void;
    clearLabel?: string;
    description?: string;
};

const DEFAULT_CANVAS_HEIGHT = 176;
const MIN_CANVAS_WIDTH = 280;

export default function SignaturePadField({
    name,
    label,
    error,
    defaultValue = null,
    value = null,
    onChange,
    clearLabel = 'Clear',
    description = 'Draw your signature inside the box.',
}: SignaturePadFieldProps) {
    const signaturePadRef = useRef<ReactSignatureCanvas | null>(null);
    const containerRef = useRef<HTMLDivElement | null>(null);
    const [canvasWidth, setCanvasWidth] = useState<number>(MIN_CANVAS_WIDTH);
    const [signatureData, setSignatureDataState] = useState<string>(
        value ?? defaultValue ?? '',
    );
    const [SignatureCanvasComponent, setSignatureCanvasComponent] =
        useState<typeof ReactSignatureCanvas | null>(null);

    const setSignatureData = (nextValue: string): void => {
        setSignatureDataState(nextValue);
        onChange?.(nextValue);
    };

    useEffect(() => {
        const nextValue = value ?? defaultValue ?? '';
        setSignatureDataState(nextValue);
    }, [value, defaultValue]);

    useEffect(() => {
        let isMounted = true;

        if (typeof window === 'undefined') {
            return () => {
                isMounted = false;
            };
        }

        void import('react-signature-canvas').then((module) => {
            if (isMounted) {
                setSignatureCanvasComponent(() => module.default);
            }
        });

        return () => {
            isMounted = false;
        };
    }, []);

    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        if (typeof ResizeObserver === 'undefined') {
            const fallbackWidth = Math.max(
                MIN_CANVAS_WIDTH,
                Math.floor(containerRef.current.clientWidth),
            );
            setCanvasWidth(fallbackWidth);

            return;
        }

        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];

            if (!entry) {
                return;
            }

            const nextWidth = Math.max(
                MIN_CANVAS_WIDTH,
                Math.floor(entry.contentRect.width),
            );

            setCanvasWidth((currentWidth) =>
                currentWidth === nextWidth ? currentWidth : nextWidth,
            );
        });

        observer.observe(containerRef.current);

        return () => {
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        const signaturePad = signaturePadRef.current;

        if (!signaturePad) {
            return;
        }

        signaturePad.clear();

        if (signatureData !== '') {
            signaturePad.fromDataURL(signatureData);
        }
    }, [signatureData, canvasWidth]);

    const handleSignatureEnd = (): void => {
        const signaturePad = signaturePadRef.current;

        if (!signaturePad || signaturePad.isEmpty()) {
            setSignatureData('');

            return;
        }

        const canvas = signaturePad.getCanvas();

        setSignatureData(canvas.toDataURL('image/png'));
    };

    const handleClear = (): void => {
        signaturePadRef.current?.clear();
        setSignatureData('');
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-3">
                <Label htmlFor={`${name}_signature_canvas`}>{label}</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleClear}
                >
                    {clearLabel}
                </Button>
            </div>

            <p className="text-xs text-muted-foreground">{description}</p>

            <div
                ref={containerRef}
                className="w-full rounded-md border border-input bg-white p-2"
            >
                {SignatureCanvasComponent ? (
                    <SignatureCanvasComponent
                        ref={signaturePadRef}
                        penColor="rgb(17,24,39)"
                        onEnd={handleSignatureEnd}
                        canvasProps={{
                            id: `${name}_signature_canvas`,
                            className: 'block w-full touch-none rounded-sm',
                            width: canvasWidth,
                            height: DEFAULT_CANVAS_HEIGHT,
                        }}
                    />
                ) : (
                    <div
                        className="w-full animate-pulse rounded-sm bg-muted/20"
                        style={{ height: `${DEFAULT_CANVAS_HEIGHT}px` }}
                    />
                )}
            </div>

            <input type="hidden" name={name} value={signatureData} readOnly />
            <InputError message={error} />
        </div>
    );
}
