import { BarcodeScanner, BarcodeFormat } from '@capacitor-mlkit/barcode-scanning';

export async function scanOnce(): Promise<string | null> {
    const { barcodes } = await BarcodeScanner.scan({
        formats: [BarcodeFormat.QrCode],
    });
    return barcodes[0]?.rawValue ?? null;
}

export async function scanQR(): Promise<string | null> {
    const perm = await BarcodeScanner.requestPermissions();
    if (perm.camera !== 'granted') return null;

    const { barcodes } = await BarcodeScanner.scan({
        formats: [BarcodeFormat.QrCode],
    });

    const raw = barcodes[0]?.rawValue || '';
    return raw || null;
}

// fallback: витягнути UUID з рядка
export function extractUuid(raw: string): string | null {
    const m = raw.match(/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i);
    return m ? m[0] : null;
}
