// ESLint flat config untuk SIKG (docs/rules/frontend.md). Dilarang melonggarkan aturan (M16).
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

const NO_FETCH = 'Panggilan jaringan hanya di features/<konteks>/api.ts atau lib/. Parse respons dengan Zod.';

export default tseslint.config(
  { ignores: ['vendor/**', 'node_modules/**', 'public/**', 'storage/**', 'bootstrap/cache/**', 'tooling/**', 'scripts/**'] },
  js.configs.recommended,
  ...tseslint.configs.strictTypeChecked,
  {
    files: ['resources/js/**/*.{ts,tsx}', 'vite.config.ts', 'vitest.config.ts'],
    languageOptions: {
      parserOptions: { projectService: true, tsconfigRootDir: process.cwd() },
      globals: { ...globals.browser },
    },
    plugins: { react, 'react-hooks': reactHooks, 'jsx-a11y': jsxA11y },
    settings: { react: { version: 'detect' } },
    rules: {
      ...react.configs.recommended.rules,
      ...react.configs['jsx-runtime'].rules,
      ...reactHooks.configs.recommended.rules,
      ...jsxA11y.flatConfigs.recommended.rules,
      'react/no-danger': 'error',
      'react/prop-types': 'off',
      'no-console': 'error',
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/ban-ts-comment': 'error',
      '@typescript-eslint/consistent-type-imports': 'error',
      '@typescript-eslint/no-floating-promises': 'error',
      '@typescript-eslint/no-misused-promises': 'error',
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
      'no-restricted-globals': ['error', { name: 'fetch', message: NO_FETCH }, { name: 'localStorage', message: 'PHI tidak boleh disimpan di peramban.' }, { name: 'sessionStorage', message: 'PHI tidak boleh disimpan di peramban.' }],
      'no-restricted-imports': ['error', { paths: [{ name: 'axios', message: NO_FETCH }], patterns: [{ group: ['redux', 'zustand', 'jotai', 'recoil', 'mobx'], message: 'State manager global dilarang tanpa ADR (M1).' }] }],
      'no-restricted-properties': ['error', { object: 'Number', property: 'parseFloat', message: 'Uang bilangan bulat (M4).' }],
    },
  },
  {
    files: ['resources/js/features/*/api.ts', 'resources/js/lib/**/*.ts'],
    rules: { 'no-restricted-globals': ['error', { name: 'localStorage', message: 'PHI tidak boleh disimpan di peramban.' }, { name: 'sessionStorage', message: 'PHI tidak boleh disimpan di peramban.' }], 'no-restricted-imports': 'off' },
  },
  {
    files: ['**/*.{test,spec}.{ts,tsx}', 'resources/js/**/__tests__/**'],
    rules: { 'no-console': 'off', '@typescript-eslint/no-non-null-assertion': 'off' },
  },
  prettier,
);
