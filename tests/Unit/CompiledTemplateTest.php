<?php

namespace Tests\Unit;

use App\Services\CampaignService;
use App\Support\TemplateRenderer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompiledTemplateTest extends TestCase
{
    public function test_compiled_source_keeps_escaping_literal_substitution_and_distinct_recipient_values(): void
    {
        $renderer = new TemplateRenderer;
        $compiled = $renderer->compile('<p>{{ contact.name }} {{ contact.email }} {{ account.name }}</p>', 'html', true);
        $this->assertSame('<p>Ana &amp; Luis a@example.test Cuenta</p>', $compiled(['contact.name' => 'Ana & Luis', 'contact.email' => 'a@example.test', 'account.name' => 'Cuenta']));
        $this->assertSame('<p>{{ account.name }}  </p>', $compiled(['contact.name' => '{{ account.name }}']));
        $this->assertSame('<p>&quot;&lt;b&gt;  </p>', $compiled(['contact.name' => '"<b>']));
        $this->assertSame('<p>  </p>', $compiled([]));
    }

    public function test_compile_rejects_unknown_malformed_and_non_text_html_tokens_before_returning_callable(): void
    {
        foreach (['<p>{{ secret }}</p>', '<p>{{ contact.name</p>', '<a href="{{ contact.email }}">x</a>', '<p style="color:{{ contact.name }}">x</p>', '<script>{{ contact.name }}</script>', '<!-- {{ contact.name }} -->', '<textarea>{{ contact.name }}</textarea>'] as $html) {
            try {
                (new TemplateRenderer)->compile($html, 'html', true);
                $this->fail('No se debe crear un callable para fuentes inválidas.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('html', $error->errors());
            }
        }
    }

    public function test_every_compiled_invocation_checks_value_type_encoding_length_and_expanded_subject(): void
    {
        $renderer = new TemplateRenderer;
        $compiled = $renderer->compile('{{ contact.name }}', 'subject');
        $this->assertSame('Válido', $compiled(['contact.name' => 'Válido']));
        foreach ([[], 123, "\xFF", str_repeat('x', 513), "Ana\r\nBcc: x@example.test", str_repeat('x', 201), '', '   '] as $value) {
            try {
                $compiled(['contact.name' => $value]);
                $this->fail('Se aceptó valor o cabecera inválidos en el callable reutilizado.');
            } catch (ValidationException $error) {
                $this->assertTrue(isset($error->errors()['values']) || isset($error->errors()['subject']));
            }
        }
        $this->assertSame('Otra persona', $compiled(['contact.name' => 'Otra persona']));
        $this->assertSame(str_repeat('á', 200), $compiled(['contact.name' => str_repeat('á', 200)]));
    }

    public function test_compiled_source_and_each_expansion_keep_byte_and_token_limits(): void
    {
        $renderer = new TemplateRenderer;
        foreach ([str_repeat('x', 100001), str_repeat('{{ contact.name }}', 201), "\xFF"] as $source) {
            try {
                $renderer->compile($source, 'text');
                $this->fail('Se aceptó una fuente fuera de límites.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('text', $error->errors());
            }
        }
        $compiled = $renderer->compile(str_repeat('x', 99000).str_repeat('{{ contact.name }}', 50), 'text');
        $this->assertSame(99050, strlen($compiled(['contact.name' => 'a'])));
        $this->assertSame(124600, strlen($compiled(['contact.name' => str_repeat('x', 512)])));
        $html = $renderer->compile(str_repeat('x', 99000).str_repeat('{{ contact.name }}', 50), 'html', true);
        try {
            $html(['contact.name' => str_repeat('&', 512)]);
            $this->fail('Se aceptó una expansión HTML de más de 200.000 bytes.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('html', $error->errors());
        }
    }

    public function test_campaign_batch_compiles_each_field_once_and_checks_each_recipient(): void
    {
        $renderer = new class extends TemplateRenderer
        {
            public array $fields = [];

            public function compile(string $source, string $field, bool $html = false): \Closure
            {
                $this->fields[] = $field;

                return parent::compile($source, $field, $html);
            }
        };
        $service = new CampaignService($renderer);
        $content = ['subject' => '{{ contact.name }}', 'html' => '<p>{{ contact.name }}</p>', 'text' => '{{ contact.email }}'];
        $recipients = [['name' => 'Ana', 'email' => 'a@example.test'], ['name' => 'Luis', 'email' => 'b@example.test']];
        $service->validateBatch($content, 'Cuenta', $recipients);
        $this->assertSame(['subject', 'html', 'text'], $renderer->fields);
        $recipients[] = ['name' => "Cabecera\r\nmaliciosa", 'email' => 'c@example.test'];
        try {
            $service->validateBatch($content, 'Cuenta', $recipients);
            $this->fail('La validación del lote omitió el último destinatario.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('subject', $error->errors());
        }
    }
}
