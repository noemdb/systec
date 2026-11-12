<?php

require_once __DIR__ . '/../../conn.php';

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testHtmlEscaperEncodesHtmlSpecialCharacters(): void
    {
        self::assertSame('&lt;script&gt;&quot;x&quot;&lt;/script&gt;', e('<script>"x"</script>'));
    }

    public function testToastNotificationSupportsAllTypes(): void
    {
        foreach (array('success', 'info', 'warning', 'danger') as $type) {
            self::assertSame(
                array('type' => $type, 'message' => 'Aviso'),
                toast_notification($type, 'Aviso')
            );
        }
    }

    public function testToastNotificationRejectsUnsupportedType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        toast_notification('unknown', 'Aviso');
    }

    public function testMaintenanceStatusTransitionRules(): void
    {
        self::assertTrue(maintenance_transition_allowed(' iniciado ', 'revision'));
        self::assertTrue(maintenance_transition_allowed('revision', 'finalizado'));
        self::assertTrue(maintenance_transition_allowed('finalizado', 'revision'));
        self::assertTrue(maintenance_transition_allowed('iniciado', 'iniciado'));
        self::assertFalse(maintenance_transition_allowed('revision', 'iniciado'));
        self::assertFalse(maintenance_transition_allowed('finalizado', 'iniciado'));
        self::assertFalse(maintenance_transition_allowed('unknown', 'finalizado'));
    }

    /**
     * @dataProvider propertyStatusCycle
     */
    public function testPropertyStatusCycle(string $current, string $next): void
    {
        self::assertSame($next, property_next_status($current));
    }

    public function propertyStatusCycle(): array
    {
        return array(
            array(' bueno ', 'MALO'),
            array('REGULAR', 'BUENO'),
            array('MALO', 'REGULAR'),
            array('MUY MALO', 'BUENO'),
            array('desconocido', 'BUENO'),
        );
    }

    /**
     * @dataProvider propertyCategories
     */
    public function testPropertyCategoryUsesPaddedGroupCodes(array $property, string $expected): void
    {
        self::assertSame($expected, property_category($property));
    }

    public function propertyCategories(): array
    {
        return array(
            array(array('grupo' => '2', 'subgrupo' => '101', 'seccion' => '1'), 'informatica'),
            array(array('grupo' => '2', 'subgrupo' => '12', 'seccion' => '5'), 'informatica'),
            array(array('grupo' => '2', 'subgrupo' => '5', 'seccion' => '8'), 'redes'),
            array(array('grupo' => '1', 'subgrupo' => '5', 'seccion' => '8'), 'otros'),
            array(array(), 'otros'),
        );
    }

    public function testPropertyCodeUsesConsistentPaddedSegments(): void
    {
        self::assertSame(
            '2-0101-0005-0012',
            property_code(array('grupo' => '2', 'subgrupo' => '101', 'seccion' => '5', 'ident' => '12'))
        );
    }

    public function testPropertyAdscriptionPrefersInstitutionalAssignment(): void
    {
        self::assertSame(
            array('key' => 'dependency:12', 'label' => 'Unidad de Informática'),
            property_adscription_assignment(
                array('ident' => '8', 'adscription' => 'Unidad local'),
                array('0008' => array('key' => 'dependency:12', 'label' => 'Unidad de Informática'))
            )
        );
    }

    public function testPropertyAdscriptionFallsBackToLocalValueOrUnassigned(): void
    {
        $localAdscription = 'Unidad de Informática';
        self::assertSame(
            array(
                'key' => 'local:' . hash('sha256', mb_strtolower($localAdscription)),
                'label' => $localAdscription,
            ),
            property_adscription_assignment(array('ident' => '8', 'adscription' => $localAdscription))
        );
        self::assertSame(
            array('key' => '__unassigned__', 'label' => 'Sin adscripción'),
            property_adscription_assignment(array('ident' => '8'))
        );
    }

    public function testPropertyStatusLabelsAreConsistent(): void
    {
        self::assertSame('Muy malo', property_status_label(' muy malo '));
        self::assertSame('Estado externo', property_status_label('Estado externo'));
    }

    public function testPersonalRoleIsAvailableAndUsesTicketsAsLandingPage(): void
    {
        $_SESSION = array();
        $_SESSION['user'] = array('id' => 1, 'username' => 'personal01', 'role' => 'personal');
        $_SESSION['intended'] = '/tickets.php';

        self::assertArrayHasKey('personal', db()->getListRoles());
        self::assertTrue(auth_can_access_ticket_for_user(1));
        self::assertFalse(auth_can_access_ticket_for_user(2));
        self::assertSame('/tickets.php', auth_intended_url());
    }

    public function testSupportRolesCanAccessAnyTicket(): void
    {
        $_SESSION = array();
        $_SESSION['user'] = array('id' => 11, 'username' => 'tecnico01', 'role' => 'tecnico');

        self::assertTrue(auth_can_manage_all_tickets());
        self::assertTrue(auth_can_access_ticket_for_user(999));
    }
}
