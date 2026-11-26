export const KESALAHAN_INPUT_CATEGORIES = [
    'Salah input IMEI',
    'Salah input kategori',
    'Salah input kapasitas',
    'Salah input tipe',
    'Salah input distributor',
    'Salah input Quantity',
    'Kesalahan input lainnya',
];

const CORRECT_DATA_PLACEHOLDERS = {
    'Salah input IMEI': 'Tuliskan nomor IMEI yang benar...',
    'Salah input kategori': 'Tuliskan kategori yang benar...',
    'Salah input kapasitas': 'Tuliskan kapasitas (RAM/Storage) yang benar...',
    'Salah input tipe': 'Tuliskan tipe/model produk yang benar...',
    'Salah input distributor': 'Tuliskan nama distributor yang benar...',
    'Salah input Quantity': 'Tuliskan jumlah (Quantity) yang benar...',
    'Kesalahan input lainnya': 'Jelaskan kesalahan dan data yang benar...',
};

export function getCorrectDataPlaceholder(category) {
    return CORRECT_DATA_PLACEHOLDERS[category] || 'Tuliskan data yang benar...';
}

export function formatDeletionReason(category, correctData) {
    const cleanData = String(correctData || '').trim();
    if (!category) return cleanData;
    return `[${category}] Data yang benar: ${cleanData}`;
}
