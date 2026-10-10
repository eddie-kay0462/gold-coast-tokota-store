<?php

namespace App\Http\Resources\Admin;

use App\Support\AdminCapability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // §17 of the brand document: the role is what the system enforces,
            // the job title is who the person is on the team.
            'job_title' => $this->job_title,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'role_label' => AdminCapability::roleLabel($this->role),
            'access_expires_at' => $this->access_expires_at,
            'has_lapsed' => $this->accessHasLapsed(),
            'access_extensions' => $this->access_extensions ?? [],
            'last_active_at' => $this->last_active_at,
            'created_at' => $this->created_at,
        ];
    }
}
