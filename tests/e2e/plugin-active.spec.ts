import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Feedwright plugin', () => {
	test( 'プラグインが有効化されている', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );
		const pluginRow = page.locator( 'tr[data-slug="feedwright"]' );
		await expect( pluginRow ).toHaveClass( /active/ );
	} );
} );
