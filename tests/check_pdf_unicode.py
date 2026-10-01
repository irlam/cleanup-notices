"""Check the PDF produced by pdf-unicode.php with pypdf."""

import sys
from pypdf import PdfReader

reader = PdfReader(sys.argv[1])
text = "\n".join(page.extract_text() for page in reader.pages)
expected = (
    "TEST — École Straße Καλημέρα Привет",
    "Málaga – Zürich № 7",
    "Zoë Łódź",
    "Unicode punctuation: — – “quotes”",
)
for value in expected:
    assert value in text, f"PDF text missing: {value!r}"
assert "â€" not in text, "PDF contains mojibake"
images_by_page = [len(page.images) for page in reader.pages]
assert images_by_page == [2, 2], (
    f"Expected header and photo on page 1, header and signature on page 2; "
    f"found {images_by_page}"
)
images = sum(images_by_page)
print(f"Unicode text and {images} embedded images verified across {len(reader.pages)} page(s)")
