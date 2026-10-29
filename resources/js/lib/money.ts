export const rupiah = (value: number) =>
    'Rp ' + new Intl.NumberFormat('id-ID').format(value);
