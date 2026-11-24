import unittest

from server import em_horario_de_brasilia


class PreviewDeadlineTest(unittest.TestCase):
    def test_deadline_crossing_midnight_utc_keeps_the_correct_date_in_brasilia(self):
        preview = {"data": {"expira_em": "2026-11-25T00:00:00.000000Z"}}

        self.assertEqual(
            em_horario_de_brasilia(preview),
            {"data": {"expira_em": "2026-11-24T21:00:00-03:00"}},
        )

    def test_deadline_already_in_brasilia_is_preserved(self):
        preview = {"expira_em": "2026-11-24T20:29:54-03:00"}

        self.assertEqual(em_horario_de_brasilia(preview), preview)
