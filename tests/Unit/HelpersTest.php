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

    public function testAssignmentStatusLabelNormalizesKnownUnknownAndEmptyValues(): void
    {
        self::assertSame('Activo', assignment_status_label(' activo '));
        self::assertSame('Remoción', assignment_status_label('REMOCION'));
        self::assertSame('Sin ficha', assignment_status_label('sin ficha'));
        self::assertSame('Comision', assignment_status_label('COMISION'));
        self::assertSame('Pendiente de sincronizar', assignment_status_label(''));
        self::assertSame('', assignment_status_label('', ''));
    }

    public function testDashboardReturnUrlPreservesWhitelistedFiltersAndRejectsInvalidValues(): void
    {
        $validHash = hash('sha256', 'ana');
        $url = dashboard_return_url(array(
            'return_category' => 'informatica',
            'return_status' => '__attention__',
            'return_adscription' => '0001/0002/00000003',
            'return_responsible' => $validHash,
            'return_assigned' => 'not-a-sha256',
            'return_search' => '0042',
        ), '9999');

        self::assertSame(
            url('dashboard.php') . '?' . http_build_query(array(
                'category' => 'informatica',
                'status' => '__attention__',
                'adscription' => '0001/0002/00000003',
                'responsible' => $validHash,
                'search' => '0042',
            )),
            $url
        );

        // Rechaza categoría/estado/adscripción inválidos y usa fallbackSearch cuando no queda ningún filtro
        $fallbackUrl = dashboard_return_url(array(
            'return_category' => '<script>',
            'return_status' => 'INVALID',
            'return_adscription' => 'bad value with spaces',
        ), '0007');
        self::assertSame(url('dashboard.php') . '?search=0007', $fallbackUrl);

        // Sin filtros ni fallback devuelve dashboard.php limpio
        self::assertSame(url('dashboard.php'), dashboard_return_url(array()));
    }

    public function testMaintenancesReturnUrlPreservesAllowedFiltersIncludingAssignments(): void
    {
        $validHash = hash('sha256', 'carlos');
        $rawQuery = '?' . http_build_query(array(
            'category' => 'informatica',
            'estado' => 'revision',
            'responsible' => $validHash,
            'assigned' => 'invalid-hash',
            'desde' => '2026-01-01',
            'hasta' => 'not-a-date',
            'vencido' => '1',
            'malicious' => 'drop-me',
        ));

        $result = maintenances_return_url($rawQuery, array('id' => 42));

        self::assertSame(
            url('maintenances.php') . '?' . http_build_query(array(
                'category' => 'informatica',
                'responsible' => $validHash,
                'estado' => 'revision',
                'desde' => '2026-01-01',
                'vencido' => '1',
                'id' => '42',
            )),
            $result
        );
        self::assertSame(url('maintenances.php'), maintenances_return_url(''));
    }

    public function testTicketsReturnUrlPreservesWhitelistedStatusAndSearchOnly(): void
    {
        $valid = tickets_return_url('?status=con_retraso&search=0042&extra=ignore');
        self::assertSame(url('tickets.php') . '?status=con_retraso&search=0042', $valid);

        $abiertos = tickets_return_url('status=abiertos');
        self::assertSame(url('tickets.php') . '?status=abiertos', $abiertos);

        $invalid = tickets_return_url('?status=invalid_code&search=');
        self::assertSame(url('tickets.php'), $invalid);
    }
}
