<?php

use App\Models\User;
use App\Notifications\Auth\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
    config(['mail.queue_auth_notifications' => false]);
});

test('el enlace de reset se envía al instante sin cola cuando queue_auth_notifications es false', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, PasswordResetLinkNotification::class);
});
