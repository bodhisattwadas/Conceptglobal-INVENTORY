<?php

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditProfile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public $profile_photo = null;

    public ?string $currentProfilePhotoPath = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->currentProfilePhotoPath = $user->profile_photo_path;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.Auth::id()],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function updateProfile(ProfileService $service): void
    {
        $validated = $this->validate();
        $validated['profile_photo_path'] = $this->profile_photo
            ? $this->profile_photo->store('profile-photos', 'public')
            : null;

        try {
            /** @var User $user */
            $user = Auth::user();

            $service->updateProfile($user, [
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'profile_photo_path' => $validated['profile_photo_path'],
            ]);

            $this->profile_photo = null;
            $this->currentProfilePhotoPath = $user->fresh()->profile_photo_path;

            $this->dispatch('profile-updated', name: $user->name);
            $this->dispatch('toast', message: 'Profile updated successfully.', type: 'success');
        } catch (\Exception $e) {
            $this->deleteUploadedPhoto($validated['profile_photo_path']);
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.profile.edit-profile');
    }

    private function deleteUploadedPhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
