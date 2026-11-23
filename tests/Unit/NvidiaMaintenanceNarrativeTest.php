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

    public function testRetryPolicyUsesIncreasingBoundedDelays(): void
    {
        self::assertSame(1, systec_nvidia_retry_delay_seconds(1));
        self::assertSame(2, systec_nvidia_retry_delay_seconds(2));
        self::assertSame(16, systec_nvidia_retry_delay_seconds(5));
        self::assertSame(30, systec_nvidia_retry_delay_seconds(7));
    }

    public function testOnlyRateLimitsAndServerErrorsAreRetryableHttpStatuses(): void
    {
        self::assertTrue(systec_nvidia_is_retryable_status(429));
        self::assertTrue(systec_nvidia_is_retryable_status(503));
        self::assertFalse(systec_nvidia_is_retryable_status(400));
        self::assertFalse(systec_nvidia_is_retryable_status(600));
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

    public function testMaintenanceValidationKeepsNotesInTheirOwnField(): void
    {
        $original = array(
            'description' => 'Se revisó la memoria RAM.',
            'failure_reason' => 'Uno de los zócalos no funciona.',
            'notes' => 'Queda pendiente revisar el navegador.',
        );
        $content = json_encode(array(
            'description' => 'Se realizó la revisión de la memoria RAM.',
            'failure_reason' => 'Uno de los zócalos de la memoria RAM no funciona.',
            'notes' => 'Aún queda pendiente revisar el navegador.',
        ), JSON_UNESCAPED_UNICODE);

        $result = systec_validate_nvidia_maintenance_texts($original, $content);

        self::assertSame('Aún queda pendiente revisar el navegador.', $result['notes']);
        self::assertStringNotContainsString('navegador', $result['description']);
        self::assertStringNotContainsString('navegador', $result['failure_reason']);
    }

    public function testTicketNarrativeValidationKeepsItsFieldsAndPreservesEmptyObservation(): void
    {
        $result = systec_validate_nvidia_texts(
            array(
                'title' => 'Equipo lento',
                'description' => 'El computador tarda en iniciar.',
                'observation' => '',
            ),
            json_encode(array(
                'title' => 'Lentitud del equipo',
                'description' => 'El computador presenta demora al iniciar.',
                'observation' => 'Texto inventado por la respuesta.',
            ), JSON_UNESCAPED_UNICODE),
            array('title', 'description', 'observation')
        );

        self::assertSame('Lentitud del equipo', $result['title']);
        self::assertSame('El computador presenta demora al iniciar.', $result['description']);
        self::assertSame('', $result['observation']);
    }

    public function testNarrativeGenerationAcceptsNewFieldsFromNarrativeInput(): void
    {
        $result = systec_validate_nvidia_texts(
            array('narrative' => 'El computador se apaga al iniciar.'),
            json_encode(array(
                'title' => 'El computador se apaga al iniciar',
                'description' => 'El usuario reporta que el computador se apaga al intentar iniciarlo.',
                'observation' => '',
            ), JSON_UNESCAPED_UNICODE),
            array('title', 'description', 'observation'),
            false,
            array('title', 'description'),
            array('title' => 255, 'description' => 5000, 'observation' => 5000)
        );

        self::assertSame('El computador se apaga al iniciar', $result['title']);
        self::assertSame('', $result['observation']);
    }

    public function testNarrativeGenerationRejectsEmptyRequiredFields(): void
    {
        $this->expectException(SystecNvidiaOutputException::class);
        systec_validate_nvidia_texts(
            array('narrative' => 'Algo sucedió.'),
            '{"title":"","description":"","observation":""}',
            array('title', 'description', 'observation'),
            false,
            array('title', 'description')
        );
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

    public function testFallbackProcessTicketNarrativeExtractsTitleDescriptionAndObservation(): void
    {
        $result = systec_fallback_process_ticket_narrative(
            "Buenos días, reporto que la impresora no imprime desde hoy.\nNota: ubicado en piso 2, extensión 1234."
        );

        self::assertTrue($result['fallback']);
        self::assertSame('La impresora no imprime desde hoy', $result['title']);
        self::assertSame('Buenos días, reporto que la impresora no imprime desde hoy.', $result['description']);
        self::assertSame('Ubicado en piso 2, extensión 1234.', $result['observation']);
    }

    public function testProcessTicketNarrativeWithFallbackUsesLocalFallbackWithoutApiKey(): void
    {
        $result = systec_process_ticket_narrative_with_fallback(
            'El monitor parpadea al encender el equipo.',
            ''
        );

        self::assertTrue($result['fallback']);
        self::assertSame('El monitor parpadea al encender el equipo', $result['title']);
        self::assertSame('El monitor parpadea al encender el equipo.', $result['description']);
        self::assertSame('', $result['observation']);
    }

    public function testImproveTicketHistoryNoteWithFallbackUsesLocalFallbackWithoutApiKey(): void
    {
        $result = systec_improve_ticket_history_note_with_fallback(
            '  se reinicio   el monitor y sigue sin señal  ',
            ''
        );

        self::assertTrue($result['fallback']);
        self::assertSame('Se reinicio el monitor y sigue sin señal.', $result['note']);
    }
}
