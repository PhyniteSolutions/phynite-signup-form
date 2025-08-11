module.exports = {
    env: {
        browser: true,
        es6: true,
        jquery: true
    },
    extends: [
        'eslint:recommended'
    ],
    parserOptions: {
        ecmaVersion: 2018,
        sourceType: 'module'
    },
    globals: {
        'wp': 'readonly',
        'phyniteSignupForm': 'readonly',
        'phyniteSignupBlockData': 'readonly',
        'phyniteAdmin': 'readonly',
        'ajaxurl': 'readonly',
        'jQuery': 'readonly',
        '$': 'readonly'
    },
    rules: {
        'indent': ['error', 4],
        'linebreak-style': ['error', 'unix'],
        'quotes': ['error', 'single'],
        'semi': ['error', 'always'],
        'no-unused-vars': ['warn'],
        'no-console': ['warn']
    }
};