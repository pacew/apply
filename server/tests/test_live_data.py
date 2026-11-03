"""Tests for the data cleaning and the cheap version checks (no database)."""

import csv
import io
import os
import sys
import tempfile
import unittest
from unittest import mock

SERVER_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if SERVER_DIR not in sys.path:
    sys.path.insert(0, SERVER_DIR)

import live_data


class ApplicationCleaningTests(unittest.TestCase):
    HEADER = [
        "BLANK", "evid", "event_title", "event_desc", "app_id", "email",
        "phone", "name_link", "name_pcode", "app_link", "notes", "C_notes",
        "C_rejection_reason", "C_rejection_notes", "P_notes", "event_type",
    ]

    def clean(self):
        row = [
            "", "T1a", "Title", "See https://example.com and mail a@b.com",
            "7", "a@b.com", "555-123-4567", "http://old.perf", "CODE",
            "https://apply.neffa.org/7", "call 555-123-4567 or https://secret.example",
            "note a@b.com", "because", "private note", "private person", "D",
        ]
        text = io.StringIO()
        writer = csv.writer(text)
        writer.writerow(self.HEADER)
        writer.writerow(row)
        body, count = live_data.clean_applications(text.getvalue())
        self.assertEqual(count, 1)
        rows = list(csv.reader(io.StringIO(body.decode("utf-8"))))
        return rows[0], rows[1]

    def test_contact_columns_are_blank(self):
        header, row = self.clean()
        for name in ("email", "phone", "name_link", "name_pcode"):
            self.assertEqual(row[header.index(name)], "", name)

    def test_public_links_are_kept(self):
        header, row = self.clean()
        self.assertEqual(row[header.index("app_link")], "https://apply.neffa.org/7")
        self.assertIn("https://example.com", row[header.index("event_desc")])

    def test_free_text_is_cleaned(self):
        header, row = self.clean()
        description = row[header.index("event_desc")]
        self.assertNotIn("a@b.com", description)
        notes = row[header.index("notes")]
        self.assertNotIn("555-123-4567", notes)
        self.assertNotIn("https://secret.example", notes)
        self.assertIn("[contact removed]", notes)
        self.assertNotIn("a@b.com", row[header.index("C_notes")])

    def test_rejection_is_a_flag_not_text(self):
        header, row = self.clean()
        self.assertEqual(row[header.index("C_rejection_reason")], "rejected")
        self.assertEqual(row[header.index("C_rejection_notes")], "")
        self.assertEqual(row[header.index("P_notes")], "")


class VersionTests(unittest.TestCase):
    def test_application_version_token(self):
        with mock.patch.object(
            live_data,
            "run_mysql",
            return_value=[["3", "2026-11-02 18:49:02", "9"]],
        ):
            version, timestamp = live_data.application_version(2027, 0, {})
        self.assertEqual(version, "3:2026-11-02 18:49:02:9")
        self.assertGreater(timestamp, 0)

    def test_timestamp_parser(self):
        self.assertEqual(live_data._parse_timestamp(""), 0)
        self.assertEqual(live_data._parse_timestamp(None), 0)
        self.assertGreater(live_data._parse_timestamp("2026-11-02 18:49:02"), 0)


class ScheduleTests(unittest.TestCase):
    def test_test_schedule_is_a_separate_file(self):
        with tempfile.TemporaryDirectory() as folder:
            with open(os.path.join(folder, "Webgrid-2027-test.tsv"), "w") as stream:
                stream.write("T1a\tTitle\n")
            cfg = {"schedule_dir": folder}
            found = live_data.get_schedule(cfg, "Webgrid-2027.tsv", "test")
            self.assertIsNotNone(found)
            self.assertIn(b"T1a", found.body)
            self.assertIsNone(live_data.get_schedule(cfg, "Webgrid-2027.tsv", "production"))

    def test_missing_schedule_is_none(self):
        with tempfile.TemporaryDirectory() as folder:
            self.assertIsNone(
                live_data.get_schedule({"schedule_dir": folder}, "Webgrid-2026.tsv", "production")
            )


if __name__ == "__main__":
    unittest.main()
