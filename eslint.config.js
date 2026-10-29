import js from '@eslint/js';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'],
        languageOptions: {
            globals: globals.browser,
        },
        settings: {
            react: { version: 'detect' },
        },
        plugins: {
            react,
            'react-hooks': reactHooks,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'react/prop-types': 'off',
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
);
