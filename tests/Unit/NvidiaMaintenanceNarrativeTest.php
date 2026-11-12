<?php

require_once __DIR__ . '/../../include/nvidia_maintenance_narrative.php';

use PHPUnit\Framework\TestCase;

class NvidiaMaintenanceNarrativeTest extends TestCase
{
    public function testChatCompletionParserExtractsMessageContent(): void
    {
        $content = systec_parse_nvidia_chat_completion_response(
            '{"choices":[{"message":{"role":"assistant","content":"{\"description\":\"Texto mejorado\",\"failure_reason\":\"\",\"notes\":\"\"}"}}]}'
        );

        self::assertSame(
            '{"description":"Texto mejorado","failure_reason":"","notes":""}',
            $content
        );
    }

    public function testApiErrorParserExtractsProviderMessage(): void
    {
        self::assertSame(
            'Modelo no disponible.',
            systec_nvidia_api_error_message('{"error":{"message":"Modelo no disponible."}}')
        );
    }

    public function testValidationPreservesEmptyFieldsAndKeepsAllExpectedKeys(): void
    {
        $original = array(
            'description' => 'Se reviso el equipo y se encontro una falla',
            'failure_reason' => '',
            'notes' => 'Pendiente verificar su funcionamiento.',
        );
        $content = json_encode(array(
            'description' => 'Se revisó el equipo y se encontró una falla.',
            'failure_reason' => 'El modelo inventa una causa.',
            'notes' => 'Queda pendiente verificar su funcionamiento.',
        ), JSON_UNESCAPED_UNICODE);

        $result = systec_validate_nvidia_maintenance_texts($original, $content);

        self::assertSame('Se revisó el equipo y se encontró una falla.', $result['description']);
        self::assertSame('', $result['failure_reason']);
        self::assertSame('Queda pendiente verificar su funcionamiento.', $result['notes']);
    }

    public function testValidationAcceptsJsonFencesAndSurroundingText(): void
    {
        $result = systec_validate_nvidia_maintenance_texts(
            array('description' => 'Original', 'failure_reason' => '', 'notes' => ''),
            "Resultado:\n```json\n{\"description\":\"Versión corregida.\",\"failure_reason\":\"\",\"notes\":\"\"}\n```"
        );

        self::assertSame('Versión corregida.', $result['description']);
    }

    public function testValidationRejectsInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        systec_validate_nvidia_maintenance_texts(
            array('description' => 'Original', 'failure_reason' => '', 'notes' => ''),
            'No puedo devolver un objeto JSON.'
        );
    }

    public function testValidationRejectsErasingExistingNarrative(): void
    {
        $this->expectException(RuntimeException::class);
        systec_validate_nvidia_maintenance_texts(
            array('description' => 'Se revisó el equipo.', 'failure_reason' => '', 'notes' => ''),
            '{"description":" ","failure_reason":"","notes":""}'
        );
    }

    public function testValidationRejectsIncompleteResponse(): void
    {
        $this->expectException(RuntimeException::class);
        systec_validate_nvidia_maintenance_texts(
            array('description' => 'Texto', 'failure_reason' => '', 'notes' => ''),
            '{"description":"Texto mejorado"}'
        );
    }
}
