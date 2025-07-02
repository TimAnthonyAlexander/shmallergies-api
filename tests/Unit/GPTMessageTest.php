<?php

namespace Tests\Unit;

use App\Services\GPTMessage;
use Tests\TestCase;

class GPTMessageTest extends TestCase
{
    /** @test */
    public function can_create_text_message()
    {
        $message = GPTMessage::text('user', 'Hello, world!');

        $this->assertInstanceOf(GPTMessage::class, $message);
        
        $array = $message->toArray();
        $this->assertEquals('user', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertCount(1, $array['content']);
        $this->assertEquals('text', $array['content'][0]['type']);
        $this->assertEquals('Hello, world!', $array['content'][0]['text']);
    }

    /** @test */
    public function can_create_text_with_image_message()
    {
        $imageBase64 = base64_encode('fake-image-data');
        $message = GPTMessage::textWithImage('user', 'Analyze this image', $imageBase64, 'image/jpeg');

        $this->assertInstanceOf(GPTMessage::class, $message);
        
        $array = $message->toArray();
        $this->assertEquals('user', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertCount(2, $array['content']);
        
        // Check text content
        $this->assertEquals('text', $array['content'][0]['type']);
        $this->assertEquals('Analyze this image', $array['content'][0]['text']);
        
        // Check image content
        $this->assertEquals('image_url', $array['content'][1]['type']);
        $this->assertEquals('data:image/jpeg;base64,' . $imageBase64, $array['content'][1]['image_url']['url']);
    }

    /** @test */
    public function defaults_to_jpeg_mime_type()
    {
        $imageBase64 = base64_encode('fake-image-data');
        $message = GPTMessage::textWithImage('user', 'Analyze this image', $imageBase64);

        $array = $message->toArray();
        $this->assertEquals('data:image/jpeg;base64,' . $imageBase64, $array['content'][1]['image_url']['url']);
    }

    /** @test */
    public function can_create_system_message()
    {
        $message = GPTMessage::text('system', 'You are a helpful assistant.');

        $array = $message->toArray();
        $this->assertEquals('system', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertEquals('text', $array['content'][0]['type']);
        $this->assertEquals('You are a helpful assistant.', $array['content'][0]['text']);
    }

    /** @test */
    public function can_create_assistant_message()
    {
        $message = GPTMessage::text('assistant', 'I understand your request.');

        $array = $message->toArray();
        $this->assertEquals('assistant', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertEquals('text', $array['content'][0]['type']);
        $this->assertEquals('I understand your request.', $array['content'][0]['text']);
    }

    /** @test */
    public function handles_different_image_formats()
    {
        $imageBase64 = base64_encode('fake-image-data');
        
        $pngMessage = GPTMessage::textWithImage('user', 'PNG image', $imageBase64, 'image/png');
        $jpegMessage = GPTMessage::textWithImage('user', 'JPEG image', $imageBase64, 'image/jpeg');
        
        $pngArray = $pngMessage->toArray();
        $jpegArray = $jpegMessage->toArray();
        
        $this->assertEquals('data:image/png;base64,' . $imageBase64, $pngArray['content'][1]['image_url']['url']);
        $this->assertEquals('data:image/jpeg;base64,' . $imageBase64, $jpegArray['content'][1]['image_url']['url']);
    }

    /** @test */
    public function can_handle_empty_text_with_image()
    {
        $imageBase64 = base64_encode('fake-image-data');
        $message = GPTMessage::textWithImage('user', '', $imageBase64);

        $array = $message->toArray();
        $this->assertEquals('', $array['content'][0]['text']);
        $this->assertEquals('data:image/jpeg;base64,' . $imageBase64, $array['content'][1]['image_url']['url']);
    }

    /** @test */
    public function can_handle_long_text_content()
    {
        $longText = str_repeat('This is a very long text message. ', 100);
        $message = GPTMessage::text('user', $longText);

        $array = $message->toArray();
        $this->assertEquals($longText, $array['content'][0]['text']);
    }

    /** @test */
    public function can_handle_special_characters()
    {
        $specialText = 'Hello! @#$%^&*()_+{}|:"<>?[];,./~`';
        $message = GPTMessage::text('user', $specialText);

        $array = $message->toArray();
        $this->assertEquals($specialText, $array['content'][0]['text']);
    }

    /** @test */
    public function can_handle_unicode_characters()
    {
        $unicodeText = 'Hello 世界! 🌍 café naïve résumé';
        $message = GPTMessage::text('user', $unicodeText);

        $array = $message->toArray();
        $this->assertEquals($unicodeText, $array['content'][0]['text']);
    }
} 