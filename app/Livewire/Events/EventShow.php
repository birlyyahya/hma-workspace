<?php

namespace App\Livewire\Events;

use Livewire\Component;

class EventShow extends Component
{

    public $guests = [
       [
         'id' => 1,
        'name' => 'John Doe',
        'jabatan' => 'Muda Wira III/b',
        'nip' => '1234567890',
        'phone' => '+1 (555) 123-4567',
        'organization' => 'Acme Corp',
        'status' => 'checked_in',
        'confirm_attendance' => 'X123X',
        'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'event_id' => 1,
        'qr_generated' => null,
       ],
         [
          'id' => 2,
          'name' => 'Jane Smith',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Acme Corp',
          'status' => 'registered',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
         ],
         [
          'id' => 3,
          'name' => 'Alice Johnson',
          'jabatan' => 'Muda Wira III/b',
          'nip' => '1234567890',
          'phone' => '+1 (555) 123-4567',
          'organization' => 'Acme Corp',
          'status' => 'cancelled',
          'confirm_attendance' => 'X123X',
          'notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
          'event_id' => 1,
          'qr_generated' => 'at',
         ],
    ];



    public function render()
    {
        return view('livewire.events.event-show');
    }
}
