import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
            'tests/e2e/accessibility-visual.spec.js-snapshots/**',
        ],
    },
    {
        files: ['**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            ...js.configs.recommended.rules,
            'no-empty': ['error', { allowEmptyCatch: true }],
            'no-unused-vars': [
                'error',
                {
                    args: 'none',
                    caughtErrors: 'none',
                    ignoreRestSiblings: true,
                },
            ],
        },
    },
];
