#!/usr/bin/env python3
"""
Fixed MCP Client Test - With proper JSON-RPC handling
"""

import asyncio
import json
import subprocess
import sys
from pathlib import Path
from typing import Dict, Any, Optional
import time

class MCPClient:
    def __init__(self, server_script_path: str):
        self.server_script_path = server_script_path
        self.process = None
        self.message_id = 0
        self.reader = None
        self.writer = None
    
    async def start_server(self):
        """Start the MCP server as a subprocess"""
        try:
            self.process = await asyncio.create_subprocess_exec(
                sys.executable, self.server_script_path,
                stdin=asyncio.subprocess.PIPE,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE
            )
            print("✓ Server started successfully")
            
            # Get stream reader/writer
            self.reader = self.process.stdout
            self.writer = self.process.stdin
            
            # Give server time to initialize
            await asyncio.sleep(0.5)
            
            # Start stderr reader task
            asyncio.create_task(self._read_stderr())
            
        except Exception as e:
            print(f"✗ Error starting server: {e}")
            raise
    
    async def _read_stderr(self):
        """Read and display stderr output from server"""
        while True:
            try:
                if self.process.stderr:
                    line = await self.process.stderr.readline()
                    if not line:
                        break
                    # Only show server debug messages if verbose
                    # print(f"[SERVER LOG] {line.decode().strip()}")
            except:
                break
    
    async def send_message(self, method: str, params: dict = None) -> Optional[Dict[str, Any]]:
        """Send a JSON-RPC message to the server and wait for response"""
        self.message_id += 1
        
        message = {
            "jsonrpc": "2.0",
            "id": self.message_id,
            "method": method,
        }
        
        if params is not None:
            message["params"] = params
        
        json_str = json.dumps(message)
        request = f"Content-Length: {len(json_str)}\r\n\r\n{json_str}"
        
        print(f"\n📤 Sending: {method} (id: {self.message_id})")
        if params:
            print(f"   Params: {json.dumps(params, indent=2)}")
        
        try:
            self.writer.write(request.encode())
            await self.writer.drain()
        except Exception as e:
            print(f"✗ Error sending: {e}")
            return None
        
        # Wait for response with matching ID
        response = await self._read_response_for_id(self.message_id)
        return response
    
    async def _read_response_for_id(self, expected_id: int, timeout: float = 5.0) -> Optional[Dict[str, Any]]:
        """Read responses until we get one with the expected ID"""
        start_time = time.time()
        
        while time.time() - start_time < timeout:
            response = await self._read_single_response()
            
            if response is None:
                await asyncio.sleep(0.1)
                continue
            
            # Check if this is a notification (no ID)
            if "id" not in response and "method" in response:
                print(f"📨 Notification: {response.get('method')}")
                continue
            
            # Check if this is our response
            if response.get("id") == expected_id:
                return response
            
            print(f"⚠️ Unexpected response ID: {response.get('id')} (expected {expected_id})")
        
        print(f"✗ Timeout waiting for response to message {expected_id}")
        return None
    
    async def _read_single_response(self) -> Optional[Dict[str, Any]]:
        """Read a single JSON-RPC response"""
        try:
            # Read Content-Length header
            header_line = await asyncio.wait_for(self.reader.readline(), timeout=0.5)
            if not header_line:
                return None
            
            header = header_line.decode().strip()
            if not header.startswith("Content-Length:"):
                print(f"⚠️ Unexpected header: {header}")
                return None
            
            content_length = int(header.split(":", 1)[1].strip())
            
            # Read empty line
            await self.reader.readline()
            
            # Read JSON content
            content = await self.reader.read(content_length)
            if not content:
                return None
            
            response = json.loads(content.decode())
            
            # Print response details
            if "result" in response:
                print(f"✅ Response received (id: {response.get('id')})")
            elif "error" in response:
                print(f"❌ Error response: {response.get('error')}")
            
            return response
            
        except asyncio.TimeoutError:
            return None
        except Exception as e:
            print(f"⚠️ Error reading response: {e}")
            return None
    
    async def initialize(self):
        """Initialize the MCP connection"""
        print("\n=== 🚀 Initializing MCP ===")
        response = await self.send_message(
            "initialize",
            {
                "protocolVersion": "2024-11-05",
                "capabilities": {},
                "clientInfo": {
                    "name": "test-client",
                    "version": "1.0.0"
                }
            }
        )
        
        if response and "result" in response:
            result = response["result"]
            print(f"✓ Initialized with server: {result.get('serverInfo', {}).get('name', 'unknown')}")
            print(f"  Version: {result.get('serverInfo', {}).get('version', 'unknown')}")
            print(f"  Protocol: {result.get('protocolVersion', 'unknown')}")
            return True
        return False
    
    async def list_tools(self):
        """List available tools"""
        print("\n=== 🔧 Listing Tools ===")
        response = await self.send_message("tools/list")
        
        if response and "result" in response:
            tools = response["result"].get("tools", [])
            print(f"Found {len(tools)} tools:")
            for tool in tools:
                print(f"  • {tool['name']}: {tool['description']}")
            return True
        return False
    
    async def call_tool(self, tool_name: str, arguments: dict):
        """Call a specific tool"""
        print(f"\n=== 🎯 Calling Tool: {tool_name} ===")
        response = await self.send_message(
            "tools/call",
            {
                "name": tool_name,
                "arguments": arguments
            }
        )
        
        if response and "result" in response:
            content = response["result"].get("content", [])
            if content and isinstance(content, list) and len(content) > 0:
                text = content[0].get('text', 'N/A')
                print(f"✓ Tool response:")
                print(f"  {text}")
            return True
        elif response and "error" in response:
            print(f"✗ Tool error: {response['error']}")
        return False
    
    async def close(self):
        """Close connection"""
        if self.process:
            print("\n[INFO] Terminating server...")
            self.process.terminate()
            try:
                await asyncio.wait_for(self.process.wait(), timeout=2)
            except asyncio.TimeoutError:
                self.process.kill()
                await self.process.wait()
            print("✓ Server terminated")

async def test_interactive():
    """Interactive test mode"""
    print("\n=== 🎮 Interactive MCP Client Test ===")
    print("Commands:")
    print("  1. write <filename> <content> - Write a file")
    print("  2. read <filename> - Read a file")
    print("  3. list - List all files")
    print("  4. quit - Exit")
    print()
    
    server_path = input("Enter path to server.py (or press Enter for default): ").strip()
    if not server_path:
        server_path = "./server_fixed.py"
    
    if not Path(server_path).exists():
        print(f"✗ File not found: {server_path}")
        return
    
    client = MCPClient(server_path)
    
    try:
        await client.start_server()
        
        if not await client.initialize():
            print("✗ Failed to initialize")
            return
        
        await client.list_tools()
        
        while True:
            print()
            cmd = input("Command> ").strip().lower()
            
            if cmd == "quit":
                break
            elif cmd.startswith("write "):
                parts = cmd[6:].split(" ", 1)
                if len(parts) == 2:
                    await client.call_tool("write_file", {
                        "filename": parts[0],
                        "content": parts[1]
                    })
                else:
                    print("Usage: write <filename> <content>")
            elif cmd.startswith("read "):
                filename = cmd[5:].strip()
                if filename:
                    await client.call_tool("read_file", {"filename": filename})
                else:
                    print("Usage: read <filename>")
            elif cmd == "list":
                await client.call_tool("list_files", {})
            else:
                print("Unknown command. Try: write, read, list, or quit")
        
    except Exception as e:
        print(f"✗ Error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        await client.close()

async def test_automated():
    """Run automated tests"""
    print("\n=== 🤖 Automated MCP Client Test ===")
    
    server_path = input("Enter path to server.py (or press Enter for default): ").strip()
    if not server_path:
        server_path = "./server_fixed.py"
    
    if not Path(server_path).exists():
        print(f"✗ File not found: {server_path}")
        return
    
    client = MCPClient(server_path)
    
    try:
        await client.start_server()
        
        # Step 1: Initialize
        print("\n📌 Test 1: Initialize connection")
        if not await client.initialize():
            print("✗ Failed to initialize")
            return
        
        # Step 2: List tools
        print("\n📌 Test 2: List available tools")
        await client.list_tools()
        
        # Step 3: Test write_file
        print("\n📌 Test 3: Write a test file")
        await client.call_tool(
            "write_file",
            {
                "filename": "test.txt",
                "content": "Hello from MCP!\nThis is a test file.\nLine 3 here!"
            }
        )
        
        # Step 4: List files
        print("\n📌 Test 4: List files in directory")
        await client.call_tool("list_files", {})
        
        # Step 5: Read file
        print("\n📌 Test 5: Read the test file")
        await client.call_tool(
            "read_file",
            {"filename": "test.txt"}
        )
        
        # Step 6: Write another file
        print("\n📌 Test 6: Write a second file")
        await client.call_tool(
            "write_file",
            {
                "filename": "data.json",
                "content": json.dumps({"test": "data", "number": 42}, indent=2)
            }
        )
        
        # Step 7: List files again
        print("\n📌 Test 7: List all files")
        await client.call_tool("list_files", {})
        
        # Test 8: Try to read non-existent file
        print("\n📌 Test 8: Try to read non-existent file (should fail)")
        await client.call_tool(
            "read_file",
            {"filename": "nonexistent.txt"}
        )
        
        print("\n✅ All tests completed!")
        
    except Exception as e:
        print(f"✗ Error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        await client.close()

async def main():
    print("Choose mode:")
    print("1. Automated tests")
    print("2. Interactive mode")
    
    choice = input("Enter choice (1 or 2): ").strip()
    
    if choice == "2":
        await test_interactive()
    else:
        await test_automated()

if __name__ == "__main__":
    asyncio.run(main())
