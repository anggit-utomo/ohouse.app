export function isTransferType(type: string): boolean {
    return type === 'transfer_in' || type === 'transfer_out';
}
