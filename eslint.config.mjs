import js from "@eslint/js";
import importPlugin from "eslint-plugin-import";
import globals from "globals";

export default [
  js.configs.recommended,
  importPlugin.flatConfigs.recommended,
  {
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "module",
      globals: {
        ...globals.browser,
        alert: "readonly",
        confirm: "readonly",
      },
    },
    rules: {
      "no-param-reassign": 0,
      "no-restricted-globals": 0,
      "no-alert": 0,
      "no-cond-assign": ["error", "except-parens"],
    },
  },
];
