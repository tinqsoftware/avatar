import asyncio
import importlib
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import AsyncMock, patch

from fastapi import Response


os.environ.setdefault("ROUTER_TOKEN", "test-token")
sys.path.insert(0, str(Path(__file__).parent))
router = importlib.import_module("app")


class RouterHealthTest(unittest.TestCase):
    def test_live_endpoint_reports_the_router_process_as_available(self) -> None:
        response = asyncio.run(router.live())

        self.assertEqual({"status": "ok"}, response)
        self.assertIn("/live", {route.path for route in router.app.routes})

    def test_health_returns_503_until_the_model_is_available(self) -> None:
        response = Response()

        with patch.object(router, "model_is_ready", new=AsyncMock(return_value=False)):
            body = asyncio.run(router.health(response))

        self.assertEqual(503, response.status_code)
        self.assertEqual(False, body["models_loaded"])

    def test_health_returns_200_after_the_model_is_available(self) -> None:
        response = Response()

        with patch.object(router, "model_is_ready", new=AsyncMock(return_value=True)):
            body = asyncio.run(router.health(response))

        self.assertEqual(200, response.status_code)
        self.assertEqual(True, body["models_loaded"])


if __name__ == "__main__":
    unittest.main()
