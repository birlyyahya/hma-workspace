import '../../vendor/masmerise/livewire-toaster/resources/js';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

// Sediakan CKEditor secara global agar komponen Alpine (editor SPD & DAR) dapat memakainya.
window.ClassicEditor = ClassicEditor;
