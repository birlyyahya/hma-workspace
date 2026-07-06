import '../../vendor/masmerise/livewire-toaster/resources/js';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Sediakan Quill secara global agar komponen Alpine (mis. editor SPD) dapat memakainya.
window.Quill = Quill;
