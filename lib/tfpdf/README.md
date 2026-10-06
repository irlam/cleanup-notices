# Unicode PDF support

This directory vendors [tFPDF 1.33](https://github.com/Setasign/tFPDF), licensed under LGPL-2.1, for UTF-8 text and subset-embedded TrueType fonts. The copied files are `tfpdf.php` and `font/unifont/ttfonts.php`; the LGPL text is in `LICENSE-LGPL-2.1.txt`. Font files `DejaVuSans.ttf` and `DejaVuSans-Bold.ttf` come from that release; their license is in `font/unifont/DejaVu_LICENSE.txt`.

`forms/clean-up/pdf.php` uses the DejaVu regular and bold fonts for all notice text. tFPDF can generate font metric cache files in `font/unifont/`; those files are ignored by Git and may be regenerated after deployment. The hosting PHP runtime needs `mbstring` and `zlib`, as required by tFPDF.
