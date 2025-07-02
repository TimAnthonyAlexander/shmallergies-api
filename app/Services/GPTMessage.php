<?php

namespace App\Services;

class GPTMessage
{
    public string $role;
    public array $content;

    public function __construct(string $role, array $content)
    {
        $this->role = $role;
        $this->content = $content;
    }

    /**
     * Create a text-only message.
     */
    public static function text(string $role, string $text): self
    {
        return new self($role, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Create a message with text and image.
     */
    public static function textWithImage(string $role, string $text, string $imageBase64, string $mimeType = 'image/jpeg'): self
    {
        return new self($role, [
            ['type' => 'text', 'text' => $text],
            [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64,{$imageBase64}",
                ],
            ],
        ]);
    }

    /**
     * Convert to array format for API request.
     */
    public function toArray(): array
    {
        return [
            'role'    => $this->role,
            'content' => $this->content,
        ];
    }
}
