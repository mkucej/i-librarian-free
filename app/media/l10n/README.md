#### ABOUT L10N DIRECTORY

1. This directory contains translation files in a PHP
   *ini* format. They should be named after their
   corresponding ICU locale codes. See:
   https://lh.2xlibre.net/locales

2. Only the language and optional country codes are
   supported. Valid examples:

        pt_PT.ini
        de.ini

#### ABOUT TRANSLATION FILES

1. All files must be encoded in UTF-8.

2. Each line must contain English text on the left,
   translated text on the right, separated by (=):

        Project = Proyecto
        New password = Nueva contraseña

3. Quotation marks are not necessary. However, if
   a particular text causes parser to fail, it must
   be enclosed in "".

4. No translation may contain these characters: {}|&~!()^"

5. Not all lines need to be translated. Missing translations
   will fall back to the English text.

6. Some English nouns and verbs are spelled the same
   (e.g. a search, to search). In those cases, a `-NOUN`,
   or `-VERB` label is appended to the word to indicate
   the intended part of speech.
   
        Search-VERB = Hľadaj
        Search-NOUN = Vyhľadávanie
