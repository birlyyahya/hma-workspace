import * as XLSX from 'xlsx';

/**
 * Utilitas import Excel sisi-frontend yang dapat dipakai ulang di halaman lain.
 * Backend TIDAK menerima file — seluruh pembacaan & validasi baris dilakukan di
 * sini, lalu hasilnya (array of object) dikirim ke endpoint bulk sebagai JSON.
 *
 * Schema kolom:
 *   { key, header, required?, type?: 'string'|'int'|'number'|'enum', map?: {..} }
 *   - key    : nama field pada payload API.
 *   - header : judul kolom pada baris pertama file Excel.
 *   - type   : cara koersi nilai sel (default 'string').
 *   - map    : untuk type 'enum', peta nilai excel (lowercase) -> nilai payload.
 */

function normalize(value) {
    return String(value ?? '').trim().toLowerCase();
}

function isBlankRow(cells) {
    return cells.every((cell) => String(cell ?? '').trim() === '');
}

function coerce(rawValue, column, rowNumber, rowErrors) {
    const str = String(rawValue ?? '').trim();

    if (str === '') {
        return column.type === 'int' || column.type === 'number' ? 0 : '';
    }

    switch (column.type) {
        case 'int':
        case 'number': {
            const num = Number(str.replace(/[^\d.-]/g, ''));
            if (Number.isNaN(num)) {
                rowErrors.push(`Baris ${rowNumber}: kolom "${column.header}" harus berupa angka.`);
                return 0;
            }
            return column.type === 'int' ? Math.trunc(num) : num;
        }
        case 'enum': {
            const mapped = column.map?.[normalize(str)];
            if (mapped === undefined) {
                rowErrors.push(`Baris ${rowNumber}: nilai "${str}" pada kolom "${column.header}" tidak dikenali.`);
                return null;
            }
            return mapped;
        }
        default:
            return str;
    }
}

/**
 * @returns {{ rows: Array<Object>, errors: string[] }}
 */
function mapMatrix(matrix, columns) {
    if (!matrix.length) {
        return { rows: [], errors: ['File kosong atau tidak memiliki data.'] };
    }

    const headerRow = matrix[0].map(normalize);
    const columnIndex = {};
    const missingHeaders = [];

    columns.forEach((column) => {
        const index = headerRow.indexOf(normalize(column.header));
        if (index === -1) {
            if (column.required !== false) {
                missingHeaders.push(column.header);
            }
        } else {
            columnIndex[column.key] = index;
        }
    });

    if (missingHeaders.length) {
        return {
            rows: [],
            errors: [`Kolom wajib tidak ditemukan: ${missingHeaders.join(', ')}. Pastikan memakai template yang disediakan.`],
        };
    }

    const rows = [];
    const errors = [];

    for (let r = 1; r < matrix.length; r++) {
        const cells = matrix[r];
        if (isBlankRow(cells)) {
            continue;
        }

        const rowNumber = r + 1;
        const rowErrors = [];
        const row = {};

        columns.forEach((column) => {
            const cell = column.key in columnIndex ? cells[columnIndex[column.key]] : '';
            const str = String(cell ?? '').trim();

            if (column.required !== false && str === '') {
                rowErrors.push(`Baris ${rowNumber}: kolom "${column.header}" wajib diisi.`);
                row[column.key] = null;
                return;
            }

            row[column.key] = coerce(cell, column, rowNumber, rowErrors);
        });

        rows.push(row);
        errors.push(...rowErrors);
    }

    if (!rows.length && !errors.length) {
        errors.push('Tidak ada baris data yang dapat dibaca.');
    }

    return { rows, errors };
}

/**
 * Baca file Excel/CSV & petakan ke array object sesuai schema kolom.
 *
 * @param {File} file
 * @param {Array} columns
 * @returns {Promise<{ rows: Array<Object>, errors: string[] }>}
 */
export function parseWorkbook(file, columns) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (event) => {
            try {
                const workbook = XLSX.read(event.target.result, { type: 'array' });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const matrix = XLSX.utils.sheet_to_json(sheet, {
                    header: 1,
                    blankrows: false,
                    defval: '',
                });
                resolve(mapMatrix(matrix, columns));
            } catch (error) {
                reject(error);
            }
        };

        reader.onerror = () => reject(new Error('Gagal membaca file.'));
        reader.readAsArrayBuffer(file);
    });
}

/**
 * Susun & unduh template .xlsx berisi baris header + contoh isian.
 *
 * @param {Array} columns
 * @param {Array<Object>} example
 * @param {string} filename
 */
export function downloadTemplate(columns, example, filename) {
    const header = columns.map((column) => column.header);
    const exampleRows = (example ?? []).map((row) => columns.map((column) => row[column.key] ?? ''));

    const worksheet = XLSX.utils.aoa_to_sheet([header, ...exampleRows]);
    worksheet['!cols'] = columns.map(() => ({ wch: 22 }));

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Template');
    XLSX.writeFile(workbook, filename || 'template.xlsx');
}
