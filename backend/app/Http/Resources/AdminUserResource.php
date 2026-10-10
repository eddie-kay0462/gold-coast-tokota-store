<?php

namespace App\Http\Resources;

use App\Support\AdminCapability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account, as `GET /admin/me` returns it.
 *
 * `capabilities` is sent rather than left for the client to derive from the
 * role. The admin app has its own copy of the matrix for hiding buttons, but
 * shipping the server's answer alongside means the two can be compared instead
 * of assumed equal — and a tier added on the server reaches the UI without a
 * frontend deploy.
 */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'role_label' => AdminCapability::roleLabel($this->role),
            'access_expires_at' => $this->access_expires_at,
            'has_lapsed' => $this->accessHasLapsed(),
            'capabilities' => $this->capabilities(),
        ];
    }
}
