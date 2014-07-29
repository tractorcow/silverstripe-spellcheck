# Spellcheck for SilverStripe

Improves spellcheck support for SilverStripe CMS, including an implementation for HunSpell.

## Installation

Ensure that your server is setup with [hunspell](http://hunspell.sourceforge.net/), and the necessary
[dictionaries](http://download.services.openoffice.org/files/contrib/dictionaries/) for each language you wish to use.

Install the spellcheck module with composer, using `composer require silverstripe/spellcheck:*`, or downloading
the module and extracting to the 'spellcheck' directory under your project root.

## Configuration

Setup the locales you wish to check for using yaml. If you do not specify any, it will default to the current
i18n default locale, and may not be appropriate if you have not configured dictionaries for some locales.

mysite/_config/config.yml

```yaml
SpellController:
  locales:
    - en_NZ
    - fr_FR
    - de_DE
```

By default only users with the `CMS_ACCESS_CMSMain` permission may perform spellchecking. This permisson
code can be altered (or at your own risk, removed) by configuring the `SpellController.required_permission` config.

```yaml
SpellController:
  # Restrict to admin only
  required_permission: 'ADMIN'
```

## Extending

Additional spell check services can be added by implementing the `SpellProvider` interface and setting this as 
the default provider using yaml.

mysite/_config/config.yml

```yaml
---
Name: myspellcheckprovider
After: '#spellcheckprovider'
---
# Set the default provider to HunSpell
Injector:
  SpellProvider: MySpellProvider
```

