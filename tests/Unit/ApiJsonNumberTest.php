<?php

namespace Tests\Unit;

use App\Support\ApiError;
use App\Support\SendingApiInput;
use App\Support\StrictApiJson;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('delivery')]
class ApiJsonNumberTest extends TestCase
{
    public function test_exact_integer_lexemes_against_independent_decimal_oracle(): void
    {
        $oracle = json_decode(file_get_contents(__DIR__.'/../Fixtures/sending-api-number-cases.json'), true, 32, JSON_THROW_ON_ERROR);
        foreach ($oracle['cases'] as $case) {
            $accepted = false;
            $integer = null;
            try {
                $value = StrictApiJson::decode('{"value":'.$case['lexeme'].'}');
                $integer = SendingApiInput::integer($value->value);
                $accepted = true;
            } catch (ApiError) {
                // Invalid JSON and a well-formed non-integer both fail acceptance;
                // HTTP status distinctions are exercised by the feature suite.
            }
            $this->assertSame($case['accepted_integer'], $accepted, $case['case'].': '.$case['lexeme']);
            if ($accepted) {
                $this->assertSame($case['normalized_integer'], $integer, $case['case']);
            }
        }
    }

    public function test_numeric_text_escapes_and_client_objects_preserve_their_types(): void
    {
        $lexeme = '1'.str_repeat('0', 2000).'e-2000';
        $this->assertSame(1, SendingApiInput::integer(StrictApiJson::decode('{"n":'.$lexeme.'}')->n));
        $this->assertSame(1, SendingApiInput::integer(StrictApiJson::decode('{"n":1e+'.str_repeat('0', 2000).'}')->n));
        $raw = '{"html":"1e3","text":"{\"sender_id\":1.1}","escaped":"\u0031e3","nested":{"lexeme":"1e0"}}';
        $decoded = StrictApiJson::decode($raw);
        $this->assertSame('1e3', $decoded->html);
        $this->assertSame('{"sender_id":1.1}', $decoded->text);
        $this->assertSame('1e3', $decoded->escaped);
        $this->assertInstanceOf(\stdClass::class, $decoded->nested);
        $this->expectException(ApiError::class);
        SendingApiInput::integer($decoded->nested);
    }

    public function test_object_property_names_and_empty_container_types_survive_decoding(): void
    {
        $raw = '{"0":"zero","01":"leading","-1":"negative","9007199254740991":"safe","emptyObject":{},"emptyArray":[],"nested":[{"0":"child"}]}';
        $decoded = StrictApiJson::decode($raw);
        $this->assertSame($raw, json_encode($decoded, JSON_THROW_ON_ERROR));
        $this->assertInstanceOf(\stdClass::class, $decoded->emptyObject);
        $this->assertSame([], $decoded->emptyArray);
        $this->assertInstanceOf(\stdClass::class, $decoded->nested[0]);
        $this->assertSame('child', $decoded->nested[0]->{'0'});
        $nulProperties = get_object_vars(StrictApiJson::decode('{"\\u0000name":1,"\\u0000*\\u0000unknown":2,"\\u0000Other\\u0000unknown":3,"x\\u0000y":4}'));
        $this->assertSame(["\0name" => 1, "\0*\0unknown" => 2, "\0Other\0unknown" => 3, "x\0y" => 4], $nulProperties);
        foreach (['{"0":1,"\\u0030":2}', '{"\\u0000x":1,"\\u0000\\u0078":2}', '{"\\u0000x":1,}', '{"\\u0000x":1} trailing'] as $invalid) {
            try {
                StrictApiJson::decode($invalid);
                $this->fail('Malformed JSON or a decoded duplicate was accepted.');
            } catch (ApiError $error) {
                $this->assertSame(400, $error->status);
            }
        }
    }
}
