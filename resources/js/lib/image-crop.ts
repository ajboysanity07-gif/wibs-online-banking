import type { Area } from 'react-easy-crop';

const DEFAULT_MAX_SIZE = 512;
const DEFAULT_QUALITY = 0.92;

const MIME_EXTENSION: Record<string, string> = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
};

const loadImage = (src: string): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();

        image.addEventListener('load', () => resolve(image));
        image.addEventListener('error', () =>
            reject(new Error('Unable to load image.')),
        );

        image.src = src;
    });

const getOutputMimeType = (mimeType?: string | null): string => {
    if (mimeType && MIME_EXTENSION[mimeType]) {
        return mimeType;
    }

    return 'image/jpeg';
};

const getOutputFileName = (
    fileName: string | null | undefined,
    mimeType: string,
): string => {
    const baseName =
        fileName && fileName.includes('.')
            ? fileName.slice(0, Math.max(0, fileName.lastIndexOf('.')))
            : fileName ?? 'profile-photo';
    const extension = MIME_EXTENSION[mimeType] ?? 'jpg';

    return `${baseName}-cropped.${extension}`;
};

export type CroppedImageResult = {
    file: File;
    blob: Blob;
    width: number;
    height: number;
};

type CreateCroppedImageOptions = {
    imageSrc: string;
    pixelCrop: Area;
    fileName?: string | null;
    mimeType?: string | null;
    maxSize?: number;
    quality?: number;
};

export const createCroppedImageFile = async ({
    imageSrc,
    pixelCrop,
    fileName,
    mimeType,
    maxSize = DEFAULT_MAX_SIZE,
    quality = DEFAULT_QUALITY,
}: CreateCroppedImageOptions): Promise<CroppedImageResult> => {
    if (pixelCrop.width <= 0 || pixelCrop.height <= 0) {
        throw new Error('Invalid crop area.');
    }

    const image = await loadImage(imageSrc);
    const outputMimeType = getOutputMimeType(mimeType);
    const longestEdge = Math.max(pixelCrop.width, pixelCrop.height);
    const scale = Math.min(1, maxSize / longestEdge);
    const outputWidth = Math.max(1, Math.round(pixelCrop.width * scale));
    const outputHeight = Math.max(1, Math.round(pixelCrop.height * scale));
    const canvas = document.createElement('canvas');

    canvas.width = outputWidth;
    canvas.height = outputHeight;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas is not supported.');
    }

    if (outputMimeType === 'image/jpeg') {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, outputWidth, outputHeight);
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        outputWidth,
        outputHeight,
    );

    const blob = await new Promise<Blob>((resolve, reject) => {
        canvas.toBlob(
            (result) => {
                if (!result) {
                    reject(new Error('Unable to create image.'));
                    return;
                }

                resolve(result);
            },
            outputMimeType,
            quality,
        );
    });

    const file = new File([blob], getOutputFileName(fileName, outputMimeType), {
        type: outputMimeType,
        lastModified: Date.now(),
    });

    return {
        file,
        blob,
        width: outputWidth,
        height: outputHeight,
    };
};
