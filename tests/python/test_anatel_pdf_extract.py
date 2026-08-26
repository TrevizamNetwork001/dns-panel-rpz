import importlib.util
import unittest
from pathlib import Path

module_path = Path(__file__).parents[2] / "scripts" / "anatel_pdf_extract.py"
spec = importlib.util.spec_from_file_location("anatel_pdf_extract", module_path)
extractor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(extractor)

class ExtractorTest(unittest.TestCase):
    def test_text_fallback_recovers_known_domains(self):
        text = "abta.org.br apachetorrent.xyz filmenoi-hd.net nickfilmestorrent.org"
        self.assertEqual(extractor.candidates(text), {
            "abta.org.br", "apachetorrent.xyz", "filmenoi-hd.net", "nickfilmestorrent.org"
        })

if __name__ == "__main__":
    unittest.main()
