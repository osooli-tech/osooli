<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ProfileSettings extends Component
{
    // Info form
    public string $name = '';

    public string $phone = '';

    // Password form
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $confirmPassword = '';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
    }

    public function saveInfo(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'name' => $this->name,
            'phone' => $this->phone ?: null,
        ]);

        $this->dispatch('swal:toast', type: 'success', message: __('profile.info_saved'));
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', Password::min(8)],
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __('profile.wrong_password'));

            return;
        }

        $user->update(['password' => Hash::make($this->newPassword)]);

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->confirmPassword = '';

        $this->dispatch('swal:toast', type: 'success', message: __('profile.password_changed'));
    }

    public function render(): View
    {
        return view('livewire.profile.profile-settings');
    }
}
