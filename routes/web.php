<?php

use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('api/wa/webhook', WhatsappWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('wa.webhook');

Route::middleware(['auth', 'verified', 'household'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('transaksi', 'pages::transaksi')->name('transaksi');
    Route::livewire('chat', 'pages::chat')->name('chat');
    Route::livewire('kategori', 'pages::kategori')->name('kategori');
    Route::livewire('laporan', 'pages::laporan')->name('laporan');
    Route::livewire('akun', 'pages::akun')->name('akun');
    Route::livewire('goals', 'pages::goals')->name('goals');
    Route::livewire('users', 'pages::users')->name('users');
});

require __DIR__.'/settings.php';
