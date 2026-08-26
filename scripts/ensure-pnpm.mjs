/**
 * Bloquea `npm install` / `yarn` en este repo. Usa pnpm.
 * Nota: `.npmrc` no debe tener `ignore-scripts=true` o este hook no corre.
 */
const ua = process.env.npm_config_user_agent ?? '';
const execPath = process.env.npm_execpath ?? '';

const isPnpm =
    ua.includes('pnpm') ||
    /pnpm/i.test(execPath) ||
    process.env.PNPM_SCRIPT_SRC_DIR !== undefined;

if (!isPnpm) {
    console.error('\nEste proyecto usa pnpm, no npm/yarn.\n');
    console.error('  corepack enable');
    console.error('  pnpm install');
    console.error('  pnpm run build\n');
    process.exit(1);
}
