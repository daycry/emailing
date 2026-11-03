"""Source hashing must reject redirection before reading a body."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
import os
import subprocess
import unittest
from unittest.mock import patch

import inventory
import test_guards as fixtures


class InventoryTests(unittest.TestCase):
    def setUp(self):fixtures.GuardTests.setUp(self)
    def tearDown(self):fixtures.GuardTests.tearDown(self)

    def test_private_basename_is_filtered_before_body_access(self):
        with patch.object(inventory.subprocess,'check_output',side_effect=[b'app/request.json\0',b'']),\
             patch.object(inventory,'digest',side_effect=AssertionError('Private body accessed.')):
            self.assertEqual(inventory.sources(),{})

    @unittest.skipUnless(os.name=='nt','Actual Windows junction boundary')
    def test_ancestor_junction_is_refused_before_hashing_external_body(self):
        root=self.directory/'repository';root.mkdir()
        (root/'app').mkdir()
        external=self.directory/'external';external.mkdir()
        (external/'Synthetic.php').write_bytes(b'<?php // Synthetic public fixture only.\n')
        junction=root/'app/alias'
        quote=lambda value:"'"+str(value).replace("'","''")+"'"
        command='New-Item -ItemType Junction -Path '+quote(junction)+' -Target '+quote(external)+' -ErrorAction Stop | Out-Null'
        result=subprocess.run(['powershell','-NoProfile','-NonInteractive','-Command',command],capture_output=True,timeout=10,
                              creationflags=subprocess.CREATE_NO_WINDOW)
        self.assertEqual(result.returncode,0)
        self.assertTrue(junction.is_junction())
        try:
            with patch.object(inventory,'ROOT',root),\
                 patch.object(inventory.subprocess,'check_output',side_effect=[b'app/alias/Synthetic.php\0',b'']),\
                 patch.object(inventory,'digest',return_value='a'*64) as body:
                with self.assertRaisesRegex(RuntimeError,'source_path_link_refused'):
                    inventory.sources()
                body.assert_not_called()
        finally:
            self.assertEqual(junction.parent.resolve(),(root/'app').resolve())
            self.assertTrue(junction.resolve().is_relative_to(self.directory.resolve()))
            os.rmdir(junction)  # Exact newly created junction only, never recursively delete its target.


if __name__=='__main__':unittest.main()
