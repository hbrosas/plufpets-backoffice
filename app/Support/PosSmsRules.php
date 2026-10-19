<?php

namespace App\Support;

class PosSmsRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^\+?[0-9]{10,13}$/'],
            'message' => ['required', 'string', 'max:'.SmsDisclaimer::bodyMax()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function template(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:'.SmsDisclaimer::bodyMax()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'number.required' => 'Enter a mobile number.',
            'number.regex' => 'Enter a valid mobile number.',
            'message.required' => 'Enter a message.',
            'message.max' => 'The message may not be longer than '.SmsDisclaimer::bodyMax().' characters.',
            'name.required' => 'Enter a template name.',
            'body.required' => 'Enter a template message.',
            'body.max' => 'The message may not be longer than '.SmsDisclaimer::bodyMax().' characters.',
        ];
    }
}
