<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Appearance settings')] class extends Component {
    use WithFileUploads;

    public ?TemporaryUploadedFile $logo = null;

    public ?TemporaryUploadedFile $favicon = null;

    public ?string $currentLogo = null;

    public ?string $currentFavicon = null;

    public function mount(): void
    {
        $this->currentLogo = AppSetting::logoUrl();
        $this->currentFavicon = AppSetting::faviconUrl();
    }

    public function saveBranding(): void
    {
        $this->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->logo) {
            $oldLogo = AppSetting::getValue('app_logo');
            $path = $this->logo->store('branding', 'public');
            AppSetting::setValue('app_logo', $path);

            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }

            $this->logo = null;
        }

        if ($this->favicon) {
            $oldFavicon = AppSetting::getValue('app_favicon');
            $path = $this->favicon->store('branding', 'public');
            AppSetting::setValue('app_favicon', $path);

            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }

            $this->favicon = null;
        }

        $this->currentLogo = AppSetting::logoUrl();
        $this->currentFavicon = AppSetting::faviconUrl();

        Flux::toast(variant: 'success', text: 'Logo aplikasi dan favicon berhasil diperbarui.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>

    <x-pages::settings.layout :heading="__('Branding Aplikasi')" :subheading="__('Upload logo aplikasi dan favicon yang akan digunakan di seluruh aplikasi.')">
        <form wire:submit="saveBranding" class="my-6 w-full space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    <flux:label>{{ __('Logo aplikasi') }}</flux:label>

                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="Preview logo aplikasi" class="h-full w-full object-contain">
                            @elseif($currentLogo)
                                <img src="{{ $currentLogo }}" alt="Logo aplikasi saat ini" class="h-full w-full object-contain">
                            @else
                                <x-app-logo-icon class="size-8 fill-current text-zinc-500" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <flux:input type="file" wire:model="logo" accept="image/*" />
                            <flux:error name="logo" />
                            <p class="mt-2 text-xs text-zinc-500">PNG, JPG, WEBP, atau SVG. Maksimal 2MB.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <flux:label>{{ __('Favicon') }}</flux:label>

                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                            @if($favicon)
                                <img src="{{ $favicon->temporaryUrl() }}" alt="Preview favicon" class="h-full w-full object-contain">
                            @elseif($currentFavicon)
                                <img src="{{ $currentFavicon }}" alt="Favicon saat ini" class="h-full w-full object-contain">
                            @else
                                <x-app-logo-icon class="size-8 fill-current text-zinc-500" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <flux:input type="file" wire:model="favicon" accept="image/*,.ico" />
                            <flux:error name="favicon" />
                            <p class="mt-2 text-xs text-zinc-500">PNG, JPG, WEBP, SVG, atau ICO. Maksimal 1MB.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Simpan branding') }}
                </flux:button>

                <span wire:loading wire:target="logo,favicon,saveBranding" class="text-sm text-zinc-500">
                    {{ __('Mengunggah...') }}
                </span>
            </div>
        </form>
    </x-pages::settings.layout>
</section>