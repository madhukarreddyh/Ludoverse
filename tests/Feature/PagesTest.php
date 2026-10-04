<?php

namespace Tests\Feature;

use App\Models\ContactMessage;

class PagesTest extends LicensedTestCase
{
    public function test_public_pages_return_200(): void
    {
        $this->get('/')->assertOk();
        $this->get('/about')->assertOk()->assertSee('About Us');
        $this->get('/contact')->assertOk()->assertSee('Contact Us');
        $this->get('/privacy-policy')->assertOk()->assertSee('Privacy Policy');
        $this->get('/refund-policy')->assertOk()->assertSee('Refund Policy');
        $this->get('/terms-and-conditions')->assertOk()->assertSee('Terms and Conditions');
    }

    public function test_contact_form_stores_message(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello, I have a question about the platform.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $message = ContactMessage::where('email', 'jane@example.com')->first();
        $this->assertNotNull($message);
        $this->assertSame('Jane Doe', $message->name);
    }

    public function test_contact_form_validates_input(): void
    {
        $response = $this->post('/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }
}
