module.exports = {
  extends: 'stylelint-config-standard-scss',
  rules: {
    // Replace CSS @ with SASS ones
    "at-rule-no-unknown": null,
    "scss/at-rule-no-unknown": true,
    // not compatible with SASS apparently
    "color-function-notation": null,
    "media-feature-range-notation": 'prefix',
    "shorthand-property-no-redundant-values": null,
    "no-descending-specificity": null
  },
}
