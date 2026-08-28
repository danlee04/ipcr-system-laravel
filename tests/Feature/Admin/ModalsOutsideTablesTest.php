<?php

namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A modal is a <div>, and a <div> is not allowed inside <tbody>.
 *
 * Put one there and the HTML parser does not simply tolerate it: it foster
 * parents the element out of the table, and the markup that was meant to stay
 * hidden until somebody asked for it ends up on the page instead. On the
 * functions list that meant every function's whole rubric - three five-level
 * scales, headed "Level" and "Description" - printed under a list that was
 * supposed to be a list of functions.
 *
 * Read as text rather than rendered: this is a fact about the templates, and
 * asserting it here says so at the place the mistake is made.
 */
class ModalsOutsideTablesTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../../resources/views/';

    /** Every list that renders a modal per row. */
    public static function rowTemplates(): array
    {
        return [
            'functions' => ['admin/functions/rows.blade.php'],
            'divisions' => ['admin/divisions/rows.blade.php'],
            'positions' => ['admin/positions/rows.blade.php'],
            'employees' => ['admin/employees/rows.blade.php'],
            'ipcrs'     => ['admin/ipcrs/rows.blade.php'],
            'my ipcr'   => ['ipcrs/show.blade.php'],
        ];
    }

    #[DataProvider('rowTemplates')]
    public function test_no_modal_is_rendered_inside_a_table(string $template): void
    {
        $path = self::VIEWS . $template;

        $this->assertFileExists($path);

        $depth = 0;
        $offenders = [];

        foreach (file($path) as $number => $line) {
            if (str_contains($line, '<x-admin.table')) {
                $depth++;
            }

            if (str_contains($line, '</x-admin.table>') || str_contains($line, '</table>')) {
                $depth = max(0, $depth - 1);
            }

            if (str_contains($line, '<table')) {
                $depth++;
            }

            if ($depth > 0 && str_contains($line, '<x-modal')) {
                $offenders[] = $number + 1;
            }
        }

        $this->assertSame([], $offenders, sprintf(
            '%s puts a modal inside a table at line %s. Move it out, below the table.',
            $template,
            implode(', ', $offenders),
        ));
    }
}
