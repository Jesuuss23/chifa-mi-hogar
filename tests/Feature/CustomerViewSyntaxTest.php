<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerViewSyntaxTest extends TestCase
{
    public static function views(): array
    {
        return [
            'checkout' => ['storefront/checkout'],
            'carousel' => ['storefront/partials/combo-carousel'],
            'combo list' => ['storefront/combos/index'],
            'storefront layout' => ['layouts/storefront'],
            'header' => ['storefront/partials/header'],
            'footer' => ['storefront/partials/footer'],
            'offer badge' => ['components/combo-offer'],
            'password field' => ['components/customer-password'],
        ];
    }

    #[DataProvider('views')]
    public function test_compiled_customer_view_has_valid_php_syntax(string $view): void
    {
        $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
        $compiled = app('blade.compiler')->compileString($source);

        // Parse the generated PHP without evaluating it or requiring view data.
        // This catches raw @php blocks accidentally consuming Blade/HTML.
        try {
            token_get_all($compiled, TOKEN_PARSE);
        } catch (\ParseError $exception) {
            $this->fail($view.' compiles to invalid PHP: '.$exception->getMessage());
        }

        $this->addToAssertionCount(1);
    }
}
