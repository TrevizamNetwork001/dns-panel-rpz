import importlib.util
import unittest
import tempfile
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

    def test_extracts_domains_from_a_real_pdf(self):
        content = b"BT /F1 12 Tf 40 100 Td (exemplo.com.br ads.example.net) Tj ET"
        objects = [
            b"<< /Type /Catalog /Pages 2 0 R >>",
            b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            b"<< /Length " + str(len(content)).encode() + b" >>\nstream\n" + content + b"\nendstream",
            b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ]
        pdf = bytearray(b"%PDF-1.4\n")
        offsets = [0]
        for index, obj in enumerate(objects, 1):
            offsets.append(len(pdf))
            pdf.extend(f"{index} 0 obj\n".encode() + obj + b"\nendobj\n")
        xref = len(pdf)
        pdf.extend(b"xref\n0 6\n0000000000 65535 f \n")
        for offset in offsets[1:]:
            pdf.extend(f"{offset:010d} 00000 n \n".encode())
        pdf.extend(f"trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode())
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "fixture.pdf"
            path.write_bytes(pdf)
            stats, domains = extractor.extract(path)
        self.assertEqual(domains, {"exemplo.com.br", "ads.example.net"})
        self.assertEqual(stats['pages'], 1)

    def test_extracts_domains_from_a_real_xlsx(self):
        from openpyxl import Workbook
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "fixture.xlsx"
            workbook = Workbook()
            workbook.active.append(["Domínio", "EXEMPLO.COM.BR"])
            workbook.active.append(["https://ads.example.net/path", None])
            workbook.save(path)
            workbook.close()
            stats, domains = extractor.extract(path)
        self.assertEqual(domains, {"exemplo.com.br", "ads.example.net"})
        self.assertEqual(stats['pages'], 1)

if __name__ == "__main__":
    unittest.main()
