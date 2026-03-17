<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * RegisterUserValidator
 *
 * Validates the registration form for new users.
 * Used by AuthController::register() before calling AuthService::register().
 *
 * Usage in AuthController:
 *
 *   public function register(Request $request, Response $response): void
 *   {
 *       $validator = new RegisterUserValidator();
 *
 *       if (!$validator->validate($this->all())) {
 *           $this->flash('errors', $validator->errors());
 *           $this->flash('old',    $this->all());
 *           return $this->redirectBack();
 *       }
 *
 *       $this->authService->register($validator->validated());
 *       $this->flash('success', 'Account created. Please log in.');
 *       return $this->redirect('/login');
 *   }
 *
 * Usage in the view to display per-field errors (auth/register.php):
 *
 *   <?php $errors = Controller::getFlash('errors') ?? []; ?>
 *   <?php $old    = Controller::getFlash('old')    ?? []; ?>
 *
 *   <input name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>">
 *   <?php if (!empty($errors['email'])): ?>
 *       <span class="error"><?= htmlspecialchars($errors['email'][0]) ?></span>
 *   <?php endif; ?>
 */
class RegisterUserValidator extends Validator
{
    protected function rules(): array
    {
        return [
            'name'                  => 'required|string|min:2|max:100',
            'email'                 => 'required|email|max:255',
            'password'              => 'required|min:8|max:72',
            'password_confirmation' => 'required',
            // 'confirmed' checks that password === password_confirmation
            // It is added on the password field, not on password_confirmation
        ];
    }

    /**
     * Additional cross-field check: password must match its confirmation.
     * We override rules() with a 'confirmed' rule on the password field.
     */
    protected function rulesWithConfirmed(): array
    {
        $rules             = $this->rules();
        $rules['password'] = 'required|min:8|max:72|confirmed';
        return $rules;
    }

    protected function messages(): array
    {
        return [
            'name.required'                  => 'Please enter your full name.',
            'name.min'                       => 'Your name must be at least 2 characters.',
            'email.required'                 => 'Please enter your email address.',
            'email.email'                    => 'That doesn\'t look like a valid email address.',
            'password.required'              => 'Please choose a password.',
            'password.min'                   => 'Your password must be at least 8 characters.',
            'password.confirmed'             => 'The passwords you entered don\'t match.',
            'password_confirmation.required' => 'Please confirm your password.',
        ];
    }
}