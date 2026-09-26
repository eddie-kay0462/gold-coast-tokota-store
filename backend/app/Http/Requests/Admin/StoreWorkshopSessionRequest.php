<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkshopType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkshopSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            // Required on create: a session with no experience attached is a
            // date and a seat count that nobody can describe to a customer,
            // which is exactly the state the booking page was stuck in before
            // §15 gave the programme a shape.
            'workshop_type_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:workshop_types,id'],
            'scheduled_date' => [$creating ? 'required' : 'sometimes', 'date', 'after_or_equal:today'],
            'scheduled_slot' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'capacity' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'location_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $typeId = $this->input('workshop_type_id')
                    ?? $this->route('workshopSession')?->workshop_type_id;
                $capacity = $this->input('capacity');

                if (! $typeId || $capacity === null) {
                    return;
                }

                $type = WorkshopType::find($typeId);

                // §15 publishes a maximum per experience and §22.15 says to
                // enforce capacity where the booking system supports it. A
                // session may offer fewer seats than the ceiling — a tour for
                // one class rather than four — but never more than the
                // programme promises the room can hold.
                if ($type && $capacity > $type->capacity) {
                    $validator->errors()->add(
                        'capacity',
                        "{$type->name} has a published maximum of {$type->capacity} participants.",
                    );
                }
            },
        ];
    }
}
