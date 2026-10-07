import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// The fixture pages are rendered by the plugin's own PHP, so the tests run against its real output
export default function globalSetup() {
  execFileSync(
    'php',
    [
      fileURLToPath(new URL('./render-fixtures.php', import.meta.url)),
      fileURLToPath(new URL('./.fixtures', import.meta.url)),
    ],
    {
      stdio: 'inherit',
    },
  );
}
