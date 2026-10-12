import js from '@eslint/js'
import globals from 'globals'
import tseslint from 'typescript-eslint'
import vue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'

export default tseslint.config(
  { ignores: ['dist', 'coverage', 'cypress/screenshots', 'cypress/videos', 'cypress/downloads'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: { parser: tseslint.parser }
    }
  },
  {
    files: ['src/**/*.{ts,vue}'],
    languageOptions: { globals: globals.browser }
  },
  {
    files: ['*.config.{js,ts}'],
    languageOptions: { globals: globals.node }
  },
  // Turns off formatting rules that would conflict with Prettier; must stay last
  prettier
)
