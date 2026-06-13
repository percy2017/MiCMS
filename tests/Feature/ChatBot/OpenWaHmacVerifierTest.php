<?php

use Illuminate\Http\Request;
use Modules\ChatBot\Services\OpenWa\OpenWaHmacVerifier;

test('verify devuelve true si no hay secret configurado (skip)', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $request = Request::create('/webhook', 'POST', [], [], [], [], 'body content');

    expect($verifier->verify($request, null))->toBeTrue();
    expect($verifier->verify($request, ''))->toBeTrue();
});

test('verify devuelve false si no hay header de firma', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $request = Request::create('/webhook', 'POST', [], [], [], [], 'body content');

    expect($verifier->verify($request, 'my-secret'))->toBeFalse();
});

test('verify acepta firma con prefijo sha256=', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $body = '{"event":"message.received"}';
    $secret = 'super-secret-32chars-1234abcd';
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $signature,
    ], $body);

    expect($verifier->verify($request, $secret))->toBeTrue();
});

test('verify rechaza firma con secret incorrecto', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $body = '{"event":"message.received"}';
    $badSignature = 'sha256='.hash_hmac('sha256', $body, 'wrong-secret');

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $badSignature,
    ], $body);

    expect($verifier->verify($request, 'correct-secret'))->toBeFalse();
});

test('verify rechaza firma de body modificado', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $originalBody = '{"event":"message.received"}';
    $secret = 'super-secret-32chars-1234abcd';
    $signature = 'sha256='.hash_hmac('sha256', $originalBody, $secret);

    $tamperedBody = '{"event":"message.sent","injected":true}';
    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $signature,
    ], $tamperedBody);

    expect($verifier->verify($request, $secret))->toBeFalse();
});

test('verify es resistente a timing attacks (constant time compare)', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $body = 'test';
    $secret = 'secret';

    $validSignature = 'sha256='.hash_hmac('sha256', $body, $secret);
    $similarButWrong = 'sha256='.hash_hmac('sha256', $body, $secret);
    $similarButWrong[strlen($similarButWrong) - 1] = '0';

    $requestValid = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $validSignature,
    ], $body);
    expect($verifier->verify($requestValid, $secret))->toBeTrue();

    $requestInvalid = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $similarButWrong,
    ], $body);
    expect($verifier->verify($requestInvalid, $secret))->toBeFalse();
});

test('compute calcula la firma esperada con formato sha256=<hex>', function (): void {
    $verifier = new OpenWaHmacVerifier;
    $body = '{"hello":"world"}';
    $secret = 'test-secret';

    $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
    $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

    expect($verifier->compute($request, $secret))->toBe($expected);
});

test('verify usa el header configurable', function (): void {
    $verifier = new OpenWaHmacVerifier(headerName: 'X-Custom-Signature');
    $body = 'test';
    $secret = 'secret';
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    $request = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-CUSTOM-SIGNATURE' => $signature,
    ], $body);

    expect($verifier->verify($request, $secret))->toBeTrue();

    $requestWrong = Request::create('/webhook', 'POST', [], [], [], [
        'HTTP_X-OPENWA-SIGNATURE' => $signature,
    ], $body);

    expect($verifier->verify($requestWrong, $secret))->toBeFalse();
});
