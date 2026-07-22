import '../../vendor/masmerise/livewire-toaster/resources/js';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import { parseWorkbook, downloadTemplate } from './excel-import';
import './project-file-uploader';

// Sediakan CKEditor secara global agar komponen Alpine (editor SPD & DAR) dapat memakainya.
window.ClassicEditor = ClassicEditor;

// Komponen Alpine reusable untuk import Excel sisi-frontend (lihat
// resources/views/components/excel-import.blade.php). Parsing & validasi baris
// dilakukan di browser; hasilnya dikirim ke Livewire sebagai array JSON.
const registerExcelImport = (Alpine) => {
    Alpine.data('excelImport', (config = {}) => ({
        columns: config.columns ?? [],
        example: config.example ?? [],
        templateName: config.templateName ?? 'template.xlsx',
        onImport: config.onImport ?? 'importParsed',
        fileName: '',
        rows: [],
        errors: [],
        parsing: false,
        submitting: false,

        get hasErrors() {
            return this.errors.length > 0;
        },

        get canImport() {
            return this.rows.length > 0 && !this.hasErrors && !this.parsing && !this.submitting;
        },

        async submit() {
            if (!this.canImport) {
                return;
            }

            this.submitting = true;

            try {
                const ok = await this.$wire[this.onImport](this.rows);
                if (ok) {
                    this.reset();
                }
            } finally {
                this.submitting = false;
            }
        },

        downloadTemplate() {
            downloadTemplate(this.columns, this.example, this.templateName);
        },

        async handleFile(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }

            this.fileName = file.name;
            this.parsing = true;
            this.rows = [];
            this.errors = [];

            try {
                const result = await parseWorkbook(file, this.columns);
                this.rows = result.rows;
                this.errors = result.errors;
            } catch (error) {
                this.errors = [error?.message || 'Gagal membaca file.'];
            } finally {
                this.parsing = false;
            }
        },

        reset() {
            this.fileName = '';
            this.rows = [];
            this.errors = [];
            this.parsing = false;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },
    }));
};

// Komponen Alpine reusable untuk field CKEditor manapun (lihat
// resources/views/components/ckeditor.blade.php). Didaftarkan global (bukan
// lewat @script per-halaman) supaya bisa dipakai dari komponen manapun tanpa
// bergantung pada halaman lain yang kebetulan me-render definisinya duluan.
const registerCkeditorField = (Alpine) => {
    Alpine.data('ckeditorField', (model, placeholder) => ({
        editor: null,
        value: model,

        init() {
            this.whenEditorReady(() => this.mountEditor(placeholder));
        },

        destroy() {
            this.editor?.destroy().catch(() => {});
            this.editor = null;
        },

        whenEditorReady(callback, tries = 0) {
            if (window.ClassicEditor) {
                callback();
                return;
            }

            if (tries > 200) {
                console.error('Editor gagal dimuat.');
                return;
            }

            setTimeout(() => this.whenEditorReady(callback, tries + 1), 50);
        },

        mountEditor(placeholder) {
            if (this.editor) {
                return;
            }

            ClassicEditor
                .create(this.$refs.editor, {
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', '|',
                        'bulletedList', 'numberedList', '|',
                        'link', 'blockQuote', '|',
                        'undo', 'redo',
                    ],
                    placeholder: placeholder || 'Tulis deskripsi...',
                })
                .then((editor) => {
                    this.editor = editor;
                    editor.setData(this.value || '');

                    editor.model.document.on('change:data', () => {
                        this.value = editor.getData();
                    });

                    this.$watch('value', (val) => {
                        if (editor.getData() !== (val || '')) {
                            editor.setData(val || '');
                        }
                    });
                })
                .catch((error) => console.error(error));
        },
    }));
};

// Daftarkan segera bila Alpine sudah aktif (mis. Livewire memulai Alpine sebelum
// modul ini dieksekusi), selain itu tunggu event alpine:init. Ini mencegah
// "excelImport is not defined" akibat urutan pemuatan skrip.
if (window.Alpine) {
    registerExcelImport(window.Alpine);
    registerCkeditorField(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => {
        registerExcelImport(window.Alpine);
        registerCkeditorField(window.Alpine);
    });
}
