<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\RegisterClient;
use App\Modules\Identity\Domain\Exceptions\ClientRegistrationDenied;
use App\Modules\Identity\Presentation\Requests\RegisterClientRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class RegisterClientController extends Controller
{
    public function __construct(
        private readonly RegisterClient $registerClient,
    ) {}

    public function __invoke(RegisterClientRequest $request): JsonResponse
    {
        $email = mb_strtolower($request->string('email')->trim()->toString());
        $rawPhone = $request->string('phone')->trim()->toString();
        $phone = $rawPhone !== '' ? $rawPhone : null;
        $name = $request->string('name')->trim()->toString();
        $password = $request->string('password')->toString();
        $birthDate = new DateTimeImmutable($request->string('birth_date')->toString());
        $consented = $request->boolean('consent');

        try {
            $userId = $this->registerClient->execute(
                email: $email,
                name: $name,
                password: $password,
                birthDate: $birthDate,
                consented: $consented,
                phone: $phone,
            );
        } catch (ClientRegistrationDenied $e) {
            $status = in_array($e->errorCode, ['EMAIL_ALREADY_REGISTERED', 'PHONE_ALREADY_REGISTERED'], true) ? 409 : 422;

            return response()->json([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $status);
        }

        return response()->json([
            'data' => [
                'id' => $userId,
                'email' => $email,
                'phone' => $phone,
                'name' => $name,
                'birth_date' => $birthDate->format('Y-m-d'),
                'is_email_verified' => false,
            ],
        ], 201);
    }
}
