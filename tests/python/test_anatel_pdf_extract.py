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

    def test_separates_portuguese_prose_glued_after_domain(self):
        text = "downloadoss5253212gift6789374.appceus777.comtodas"
        self.assertEqual(extractor.candidates(text), {
            "downloadoss5253212gift6789374.appceus777.com"
        })

    def test_does_not_trim_a_legitimate_domain(self):
        self.assertEqual(extractor.candidates("servico.company"), {"servico.company"})

if __name__ == "__main__":
    unittest.main()
