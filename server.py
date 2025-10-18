#!/usr/bin/env python3
"""
Fixed Simple File MCP Server - With proper JSON-RPC handling
"""

import json
import os
import sys
import asyncio
from pathlib import Path
from typing import Any, Dict, List, Optional
import traceback

# Define the working directory
WORKING_DIR = Path("./mcp_sandbox")
WORKING_DIR.mkdir(exist_ok=True)

class SimpleMCPServer:
    def __init__(self):
        self.initialized = False
        print(f"[SERVER] Starting Simple File MCP Server...", file=sys.stderr)
        print(f"[SERVER] Sandbox directory: {WORKING_DIR.resolve()}", file=sys.stderr)
    
    async def handle_initialize(self, params: Dict[str, Any]) -> Dict[str, Any]:
        """Handle initialization request"""
        print(f"[SERVER] Initialize called with params: {params}", file=sys.stderr)
        self.initialized = True
        return {
            "protocolVersion": "2024-11-05",
            "capabilities": {
                "tools": {
                    "listChanged": False
                }
            },
            "serverInfo": {
                "name": "simple-file-server",
                "version": "0.2.0"
            }
        }
    
    async def handle_list_tools(self) -> Dict[str, Any]:
        """Handle tools/list request"""
        print(f"[SERVER] List tools called", file=sys.stderr)
        tools = [
            {
                "name": "write_file",
                "description": "Write content to a file in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "filename": {
                            "type": "string",
                            "description": "Name of the file to write"
                        },
                        "content": {
                            "type": "string", 
                            "description": "Content to write to the file"
                        }
                    },
                    "required": ["filename", "content"]
                }
            },
            {
                "name": "read_file",
                "description": "Read content from a file in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "filename": {
                            "type": "string",
                            "description": "Name of the file to read"
                        }
                    },
                    "required": ["filename"]
                }
            },
            {
                "name": "list_files",
                "description": "List all files in the sandbox directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {}
                }
            }
        ]
        return {"tools": tools}
    
    async def handle_call_tool(self, name: str, arguments: Dict[str, Any]) -> Dict[str, Any]:
        """Handle tools/call request"""
        print(f"[SERVER] Tool called: {name} with args: {arguments}", file=sys.stderr)
        
        if name == "write_file":
            filename = arguments.get("filename")
            content = arguments.get("content", "")
            
            if not filename:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": "Error: filename is required"
                        }
                    ]
                }
            
            filepath = WORKING_DIR / filename
            
            # Security check
            try:
                if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                    return {
                        "content": [
                            {
                                "type": "text",
                                "text": "Error: Invalid filename. File must be in sandbox directory."
                            }
                        ]
                    }
            except ValueError:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": "Error: Invalid filename path"
                        }
                    ]
                }
            
            try:
                filepath.write_text(content, encoding='utf-8')
                msg = f"Successfully wrote {len(content)} characters to {filename}"
                print(f"[SERVER] {msg}", file=sys.stderr)
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": msg
                        }
                    ]
                }
            except Exception as e:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": f"Error writing file: {str(e)}"
                        }
                    ]
                }
        
        elif name == "read_file":
            filename = arguments.get("filename")
            
            if not filename:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": "Error: filename is required"
                        }
                    ]
                }
            
            filepath = WORKING_DIR / filename
            
            # Security check
            try:
                if not filepath.resolve().is_relative_to(WORKING_DIR.resolve()):
                    return {
                        "content": [
                            {
                                "type": "text",
                                "text": "Error: Invalid filename. File must be in sandbox directory."
                            }
                        ]
                    }
            except ValueError:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": "Error: Invalid filename path"
                        }
                    ]
                }
            
            try:
                content = filepath.read_text(encoding='utf-8')
                print(f"[SERVER] Read {len(content)} characters from {filename}", file=sys.stderr)
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": content
                        }
                    ]
                }
            except FileNotFoundError:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": f"Error: File '{filename}' not found"
                        }
                    ]
                }
            except Exception as e:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": f"Error reading file: {str(e)}"
                        }
                    ]
                }
        
        elif name == "list_files":
            try:
                files = [f.name for f in WORKING_DIR.iterdir() if f.is_file()]
                if files:
                    file_list = "\n".join(f"- {f}" for f in sorted(files))
                    msg = f"Files in sandbox:\n{file_list}"
                else:
                    msg = "No files in sandbox directory yet"
                print(f"[SERVER] Found {len(files)} files", file=sys.stderr)
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": msg
                        }
                    ]
                }
            except Exception as e:
                return {
                    "content": [
                        {
                            "type": "text",
                            "text": f"Error listing files: {str(e)}"
                        }
                    ]
                }
        
        else:
            return {
                "content": [
                    {
                        "type": "text",
                        "text": f"Unknown tool: {name}"
                    }
                ]
            }
    
    async def process_request(self, request: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Process a JSON-RPC request and return a response"""
        method = request.get("method")
        params = request.get("params", {})
        request_id = request.get("id")
        
        print(f"[SERVER] Processing request: method={method}, id={request_id}", file=sys.stderr)
        
        try:
            result = None
            
            if method == "initialize":
                result = await self.handle_initialize(params)
            elif method == "tools/list":
                result = await self.handle_list_tools()
            elif method == "tools/call":
                name = params.get("name", "")
                arguments = params.get("arguments", {})
                result = await self.handle_call_tool(name, arguments)
            else:
                # Unknown method - return error
                return {
                    "jsonrpc": "2.0",
                    "id": request_id,
                    "error": {
                        "code": -32601,
                        "message": f"Method not found: {method}"
                    }
                }
            
            # Return success response
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "result": result
            }
            
        except Exception as e:
            print(f"[SERVER] Error processing request: {e}", file=sys.stderr)
            traceback.print_exc(file=sys.stderr)
            return {
                "jsonrpc": "2.0",
                "id": request_id,
                "error": {
                    "code": -32603,
                    "message": "Internal error",
                    "data": str(e)
                }
            }
    
    def send_message(self, message: Dict[str, Any]):
        """Send a JSON-RPC message over stdout with proper framing"""
        json_str = json.dumps(message)
        output = f"Content-Length: {len(json_str)}\r\n\r\n{json_str}"
        sys.stdout.write(output)
        sys.stdout.flush()
        print(f"[SERVER] Sent response: {message.get('id', 'notification')}", file=sys.stderr)
    
    async def read_message(self) -> Optional[Dict[str, Any]]:
        """Read a JSON-RPC message from stdin with proper framing"""
        try:
            # Read Content-Length header
            header_line = sys.stdin.readline()
            if not header_line:
                return None
            
            if "Content-Length:" not in header_line:
                print(f"[SERVER] Invalid header: {header_line.strip()}", file=sys.stderr)
                return None
            
            content_length = int(header_line.split(":", 1)[1].strip())
            print(f"[SERVER] Reading message with length: {content_length}", file=sys.stderr)
            
            # Read empty line
            sys.stdin.readline()
            
            # Read JSON content
            content = sys.stdin.read(content_length)
            if not content:
                return None
            
            message = json.loads(content)
            print(f"[SERVER] Received message: method={message.get('method')}, id={message.get('id')}", file=sys.stderr)
            return message
            
        except Exception as e:
            print(f"[SERVER] Error reading message: {e}", file=sys.stderr)
            traceback.print_exc(file=sys.stderr)
            return None
    
    async def run(self):
        """Main server loop"""
        print(f"[SERVER] Server ready, waiting for messages...", file=sys.stderr)
        
        while True:
            try:
                # Read next message
                message = await self.read_message()
                if message is None:
                    print(f"[SERVER] No more messages, shutting down", file=sys.stderr)
                    break
                
                # Process request and get response
                response = await self.process_request(message)
                
                # Send response if there is one (not for notifications)
                if response:
                    self.send_message(response)
                
            except KeyboardInterrupt:
                print(f"[SERVER] Interrupted, shutting down", file=sys.stderr)
                break
            except Exception as e:
                print(f"[SERVER] Unexpected error: {e}", file=sys.stderr)
                traceback.print_exc(file=sys.stderr)
                # Try to continue
                continue

async def main():
    server = SimpleMCPServer()
    await server.run()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print(f"[SERVER] Shutdown complete", file=sys.stderr)
